<?php
declare(strict_types=1);
$active_page=basename($_SERVER['PHP_SELF']??''); $user=function_exists('current_user')?current_user():null;
$nav_groups = [
    'OVERVIEW' => [
        ['href' => 'dashboard.php', 'label' => 'Dashboard'],
    ],
    'DAILY MONEY' => [
        ['href' => 'transactions.php', 'label' => 'Transactions'],
        ['href' => 'irregular-income.php', 'label' => 'Income plan'],
    ],
    'PLAN AHEAD' => [
        ['href' => 'budgets.php', 'label' => 'Budgets'],
        ['href' => 'savings.php', 'label' => 'Savings goals'],
        ['href' => 'debts.php', 'label' => 'Debt payoff'],
    ],
    'UNDERSTAND' => [
        ['href' => 'cashflow.php', 'label' => 'Cash flow'],
        ['href' => 'analytics.php', 'label' => 'Analytics'],
        ['href' => 'reports.php', 'label' => 'Reports & statements'],
    ],
    'TOOLS & HELP' => [
        ['href' => 'ai-assistant.php', 'label' => 'Ask Imali'],
        ['href' => 'help.php', 'label' => 'Help center'],
        ['href' => 'faq.php', 'label' => 'FAQ'],
    ],
];
?>
<aside class="sidebar" id="app-sidebar" data-sidebar aria-label="Personal finance navigation"><div class="brand-lockup"><a href="dashboard.php" class="brand-mark" aria-label="Imali Smart Overview" style="display:flex;align-items:center;gap:10px;text-decoration:none;"><img src="assets/logo/imali-logo.png" alt="Imali Smart" style="max-height:44px;width:auto;object-fit:contain;border-radius:6px;"></a><button class="icon-button sidebar-close" type="button" data-mobile-menu aria-label="Close navigation">×</button></div>
<a class="sidebar-user-pill" href="settings.php" style="text-decoration:none;padding:10px 14px;margin:12px 14px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:10px;display:flex;align-items:center;gap:10px;"><span class="avatar-badge" style="width:32px;height:32px;border-radius:50%;background:var(--marigold);color:#000;display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;"><?=e(strtoupper(substr((string)($user['full_name']??'U'),0,1)))?></span><div style="flex:1;min-width:0;"><div style="font-size:13px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=e((string)($user['full_name']??'Verified User'))?></div><div style="font-size:11px;color:var(--ink-faint);">Your money confidence workspace</div></div></a>
<nav class="primary-nav" aria-label="Personal finance sections"><?php $firstGroup=true; foreach($nav_groups as $groupName=>$groupItems):?><p class="nav-label<?=$firstGroup?'':' nav-label--spaced'?>"><?=e($groupName)?></p><?php $firstGroup=false; foreach($groupItems as $item):?><a class="nav-item<?=$active_page===$item['href']?' is-active':''?>"<?=$active_page===$item['href']?' aria-current="page"':''?> href="<?=e($item['href'])?>"><span><?=e($item['label'])?></span></a><?php endforeach; endforeach;?><p class="nav-label nav-label--spaced">ACCOUNT</p><a class="nav-item sidebar-settings<?=$active_page==='settings.php'?' is-active':''?>" href="settings.php"><span><?=e(t('nav_settings'))?></span></a></nav>
<div class="sidebar-options" aria-label="Display options"><div class="sidebar-options__row" role="group" aria-label="Text size"><button type="button" class="sidebar-chip" data-a11y-text="md" aria-pressed="true" style="font-size:12px;">A</button><button type="button" class="sidebar-chip" data-a11y-text="lg" aria-pressed="false" style="font-size:15px;">A</button><button type="button" class="sidebar-chip" data-a11y-text="xl" aria-pressed="false" style="font-size:18px;">A</button></div><div class="theme-seg theme-seg--side" role="group" aria-label="Appearance"><button class="theme-seg__btn" type="button" data-set-theme="light" aria-pressed="false" title="<?=e(t('theme_light'))?>" aria-label="<?=e(t('theme_light'))?>"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4m11.4-11.4 1.4-1.4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span><?=e(t('theme_light'))?></span></button><button class="theme-seg__btn" type="button" data-set-theme="dark" aria-pressed="false" title="<?=e(t('theme_dark'))?>" aria-label="<?=e(t('theme_dark'))?>"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M20.5 14.2A8.5 8.5 0 0 1 9.8 3.5a8.5 8.5 0 1 0 10.7 10.7z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg><span><?=e(t('theme_dark'))?></span></button><button class="theme-seg__btn" type="button" data-set-theme="default" aria-pressed="false" title="<?=e(t('theme_system'))?>" aria-label="<?=e(t('theme_system'))?>"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 20h8M12 16v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span><?=e(t('theme_system'))?></span></button></div></div><div class="sidebar-footer"><a class="sidebar-logout" href="logout.php" style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;color:#ef4444;text-decoration:none;border-radius:8px;font-weight:600;font-size:13px;background:rgba(239,68,68,.08);"><span><?=e(t('logout'))?></span><span>↪</span></a></div></aside><div class="sidebar-scrim" data-mobile-menu></div>
