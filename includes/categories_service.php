<?php
// Imali Smart - add, edit, delete and restore income/expense categories.
//
// The standard categories are shared by every user (user_id IS NULL), so they are never changed
// directly. When a user edits or deletes a standard category, it is copied or hidden for that user only:
//   edit   -> a personal copy with the new name/type is created, the user's transactions and budgets move to it,
//             and the standard one is hidden for that user
//   delete -> the standard one is hidden for that user and their transactions/budgets become uncategorised
// "Restore standard categories" shows the hidden ones again.
declare(strict_types=1);

/** Creates the small table that remembers which standard categories a user has hidden. */
function category_ensure_tables(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS user_hidden_categories (
        user_id INTEGER NOT NULL,
        category_id INTEGER NOT NULL,
        PRIMARY KEY (user_id, category_id)
    )');
}

/** Category list for one user (own + standard that are not hidden), with how many transactions use each. */
function category_list(PDO $pdo, int $userId): array
{
    category_ensure_tables($pdo);
    $q = $pdo->prepare('
        SELECT c.id, c.user_id, c.name, c.category_type, c.is_system,
               (SELECT COUNT(*) FROM transactions t WHERE t.category_id = c.id AND t.user_id = :u1 AND t.deleted_at IS NULL) AS used
        FROM expense_categories c
        WHERE (c.user_id IS NULL OR c.user_id = :u2)
          AND c.id NOT IN (SELECT category_id FROM user_hidden_categories WHERE user_id = :u3)
        ORDER BY c.is_system DESC, c.name ASC');
    $q->execute(['u1' => $userId, 'u2' => $userId, 'u3' => $userId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

function category_hidden_count(PDO $pdo, int $userId): int
{
    category_ensure_tables($pdo);
    $q = $pdo->prepare('SELECT COUNT(*) FROM user_hidden_categories WHERE user_id = :u');
    $q->execute(['u' => $userId]);
    return (int)$q->fetchColumn();
}

/** Cleans a name typed by the user. Returns [name, error]. */
function category_clean_name(string $name): array
{
    $name = trim((string)preg_replace('/\s+/u', ' ', $name));
    if ($name === '') return ['', 'Please type a category name.'];
    if (mb_strlen($name) > 80) return ['', 'The category name is too long (80 characters at most).'];
    if (preg_match('/[<>]/', $name)) return ['', 'Please leave out the < and > characters.'];
    return [$name, ''];
}

function category_clean_type(mixed $type): string
{
    return in_array($type, ['income', 'expense', 'both'], true) ? (string)$type : 'expense';
}

/** True if the user already sees a category with this name (ignoring capitals), apart from $exceptId. */
function category_name_taken(PDO $pdo, int $userId, string $name, int $exceptId = 0): bool
{
    foreach (category_list($pdo, $userId) as $c) {
        if ((int)$c['id'] !== $exceptId && mb_strtolower((string)$c['name']) === mb_strtolower($name)) return true;
    }
    return false;
}

/** Loads one category the user may see, or null. */
function category_find(PDO $pdo, int $userId, int $id): ?array
{
    foreach (category_list($pdo, $userId) as $c) {
        if ((int)$c['id'] === $id) return $c;
    }
    return null;
}

/** @return array{0:bool,1:string} [ok, message] */
function category_create(PDO $pdo, int $userId, string $name, mixed $type): array
{
    [$name, $err] = category_clean_name($name);
    if ($err !== '') return [false, $err];
    if (category_name_taken($pdo, $userId, $name)) return [false, "You already have a category called '{$name}'."];
    $st = $pdo->prepare('INSERT INTO expense_categories (user_id, name, category_type, is_system) VALUES (:u, :n, :t, 0)');
    $st->execute(['u' => $userId, 'n' => $name, 't' => category_clean_type($type)]);
    return [true, "Category '{$name}' added."];
}

/** @return array{0:bool,1:string} */
function category_update(PDO $pdo, int $userId, int $id, string $name, mixed $type): array
{
    [$name, $err] = category_clean_name($name);
    if ($err !== '') return [false, $err];
    $type = category_clean_type($type);
    $cat = category_find($pdo, $userId, $id);
    if (!$cat) return [false, 'That category was not found.'];
    if ($name === (string)$cat['name'] && $type === (string)$cat['category_type']) return [true, 'No changes were made.'];
    if (category_name_taken($pdo, $userId, $name, $id)) return [false, "You already have a category called '{$name}'."];

    $pdo->beginTransaction();
    try {
        if ((int)$cat['is_system'] === 0) {
            $st = $pdo->prepare('UPDATE expense_categories SET name = :n, category_type = :t WHERE id = :id AND user_id = :u AND is_system = 0');
            $st->execute(['n' => $name, 't' => $type, 'id' => $id, 'u' => $userId]);
        } else {
            // Standard category: make a personal copy and move this user's records to it.
            $ins = $pdo->prepare('INSERT INTO expense_categories (user_id, name, category_type, is_system) VALUES (:u, :n, :t, 0)');
            $ins->execute(['u' => $userId, 'n' => $name, 't' => $type]);
            $newId = (int)$pdo->lastInsertId();
            foreach (['transactions', 'budgets'] as $table) {
                $mv = $pdo->prepare("UPDATE {$table} SET category_id = :new WHERE category_id = :old AND user_id = :u");
                $mv->execute(['new' => $newId, 'old' => $id, 'u' => $userId]);
            }
            $hide = $pdo->prepare('INSERT INTO user_hidden_categories (user_id, category_id) VALUES (:u, :c)');
            $hide->execute(['u' => $userId, 'c' => $id]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return [true, "Category '{$name}' saved."];
}

/** @return array{0:bool,1:string} */
function category_delete(PDO $pdo, int $userId, int $id): array
{
    $cat = category_find($pdo, $userId, $id);
    if (!$cat) return [false, 'That category was not found.'];

    $pdo->beginTransaction();
    try {
        foreach (['transactions', 'budgets'] as $table) {
            $un = $pdo->prepare("UPDATE {$table} SET category_id = NULL WHERE category_id = :c AND user_id = :u");
            $un->execute(['c' => $id, 'u' => $userId]);
        }
        if ((int)$cat['is_system'] === 0) {
            $del = $pdo->prepare('DELETE FROM expense_categories WHERE id = :id AND user_id = :u AND is_system = 0');
            $del->execute(['id' => $id, 'u' => $userId]);
        } else {
            $hide = $pdo->prepare('INSERT INTO user_hidden_categories (user_id, category_id) VALUES (:u, :c)');
            $hide->execute(['u' => $userId, 'c' => $id]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    $used = (int)$cat['used'];
    return [true, "Category '{$cat['name']}' deleted." . ($used > 0 ? " {$used} transaction(s) are now uncategorised." : '')];
}

/** Shows the standard categories the user hid again (skips any whose name the user now uses). @return array{0:bool,1:string} */
function category_restore_defaults(PDO $pdo, int $userId): array
{
    category_ensure_tables($pdo);
    $visible = array_map(static fn($c) => mb_strtolower((string)$c['name']), category_list($pdo, $userId));
    $q = $pdo->prepare('SELECT c.id, c.name FROM user_hidden_categories h JOIN expense_categories c ON c.id = h.category_id WHERE h.user_id = :u');
    $q->execute(['u' => $userId]);
    $restored = 0;
    $del = $pdo->prepare('DELETE FROM user_hidden_categories WHERE user_id = :u AND category_id = :c');
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (in_array(mb_strtolower((string)$row['name']), $visible, true)) continue;
        $del->execute(['u' => $userId, 'c' => $row['id']]);
        $restored++;
    }
    return [true, $restored > 0 ? "{$restored} standard categories restored." : 'There are no standard categories to restore.'];
}
