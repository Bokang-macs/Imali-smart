<?php
// The currencies people can choose from (registration and settings), grouped by region.
declare(strict_types=1);

// Returns region => [ CODE => ['name' => ..., 'symbol' => ...] ].
function supported_currencies(): array
{
    static $list = null;
    if ($list !== null) return $list;

    $raw = [
        'South Africa & Southern Africa' => [
            ['ZAR', 'South African Rand', 'R'], ['BWP', 'Botswana Pula', 'P'], ['NAD', 'Namibian Dollar', 'N$'],
            ['LSL', 'Lesotho Loti', 'L'], ['SZL', 'Eswatini Lilangeni', 'E'], ['MZN', 'Mozambican Metical', 'MT'],
            ['ZMW', 'Zambian Kwacha', 'K'], ['ZWG', 'Zimbabwe Gold', 'ZiG'], ['MWK', 'Malawian Kwacha', 'MK'],
            ['AOA', 'Angolan Kwanza', 'Kz'], ['MGA', 'Malagasy Ariary', 'Ar'], ['MUR', 'Mauritian Rupee', '₨'],
            ['SCR', 'Seychellois Rupee', '₨'],
        ],
        'East Africa' => [
            ['KES', 'Kenyan Shilling', 'KSh'], ['UGX', 'Ugandan Shilling', 'USh'], ['TZS', 'Tanzanian Shilling', 'TSh'],
            ['RWF', 'Rwandan Franc', 'FRw'], ['ETB', 'Ethiopian Birr', 'Br'], ['BIF', 'Burundian Franc', 'FBu'],
            ['SSP', 'South Sudanese Pound', 'SSP'], ['SOS', 'Somali Shilling', 'Sh'], ['DJF', 'Djiboutian Franc', 'Fdj'],
            ['ERN', 'Eritrean Nakfa', 'Nfk'],
        ],
        'West & Central Africa' => [
            ['NGN', 'Nigerian Naira', '₦'], ['GHS', 'Ghanaian Cedi', 'GH₵'], ['XOF', 'West African CFA Franc', 'CFA'],
            ['XAF', 'Central African CFA Franc', 'FCFA'], ['GMD', 'Gambian Dalasi', 'D'], ['SLE', 'Sierra Leonean Leone', 'Le'],
            ['LRD', 'Liberian Dollar', 'L$'], ['GNF', 'Guinean Franc', 'FG'], ['CDF', 'Congolese Franc', 'FC'],
            ['CVE', 'Cape Verdean Escudo', '$'],
        ],
        'North Africa' => [
            ['EGP', 'Egyptian Pound', 'E£'], ['MAD', 'Moroccan Dirham', 'DH'], ['TND', 'Tunisian Dinar', 'DT'],
            ['DZD', 'Algerian Dinar', 'DA'], ['LYD', 'Libyan Dinar', 'LD'], ['SDG', 'Sudanese Pound', 'SDG'],
        ],
        'Americas' => [
            ['USD', 'US Dollar', '$'], ['CAD', 'Canadian Dollar', 'C$'], ['MXN', 'Mexican Peso', 'Mex$'],
            ['BRL', 'Brazilian Real', 'R$'], ['ARS', 'Argentine Peso', 'AR$'], ['CLP', 'Chilean Peso', 'CLP$'],
            ['COP', 'Colombian Peso', 'COL$'], ['PEN', 'Peruvian Sol', 'S/'], ['UYU', 'Uruguayan Peso', '$U'],
            ['JMD', 'Jamaican Dollar', 'J$'], ['TTD', 'Trinidad & Tobago Dollar', 'TT$'], ['BBD', 'Barbadian Dollar', 'Bds$'],
            ['DOP', 'Dominican Peso', 'RD$'], ['GTQ', 'Guatemalan Quetzal', 'Q'], ['CRC', 'Costa Rican Colón', '₡'],
        ],
        'Europe' => [
            ['EUR', 'Euro', '€'], ['GBP', 'British Pound', '£'], ['CHF', 'Swiss Franc', 'CHF'],
            ['SEK', 'Swedish Krona', 'kr'], ['NOK', 'Norwegian Krone', 'kr'], ['DKK', 'Danish Krone', 'kr'],
            ['ISK', 'Icelandic Króna', 'kr'], ['PLN', 'Polish Złoty', 'zł'], ['CZK', 'Czech Koruna', 'Kč'],
            ['HUF', 'Hungarian Forint', 'Ft'], ['RON', 'Romanian Leu', 'lei'], ['BGN', 'Bulgarian Lev', 'лв'],
            ['TRY', 'Turkish Lira', '₺'], ['RUB', 'Russian Ruble', '₽'], ['UAH', 'Ukrainian Hryvnia', '₴'],
        ],
        'Middle East' => [
            ['AED', 'UAE Dirham', 'د.إ'], ['SAR', 'Saudi Riyal', 'ر.س'], ['QAR', 'Qatari Riyal', 'QR'],
            ['KWD', 'Kuwaiti Dinar', 'KD'], ['BHD', 'Bahraini Dinar', 'BD'], ['OMR', 'Omani Rial', 'OMR'],
            ['JOD', 'Jordanian Dinar', 'JD'], ['ILS', 'Israeli Shekel', '₪'],
        ],
        'Asia' => [
            ['CNY', 'Chinese Yuan', '¥'], ['JPY', 'Japanese Yen', '¥'], ['INR', 'Indian Rupee', '₹'],
            ['PKR', 'Pakistani Rupee', '₨'], ['BDT', 'Bangladeshi Taka', '৳'], ['LKR', 'Sri Lankan Rupee', 'Rs'],
            ['NPR', 'Nepalese Rupee', 'Rs'], ['IDR', 'Indonesian Rupiah', 'Rp'], ['MYR', 'Malaysian Ringgit', 'RM'],
            ['SGD', 'Singapore Dollar', 'S$'], ['THB', 'Thai Baht', '฿'], ['VND', 'Vietnamese Dong', '₫'],
            ['PHP', 'Philippine Peso', '₱'], ['KRW', 'South Korean Won', '₩'], ['HKD', 'Hong Kong Dollar', 'HK$'],
            ['TWD', 'New Taiwan Dollar', 'NT$'],
        ],
        'Oceania' => [
            ['AUD', 'Australian Dollar', 'A$'], ['NZD', 'New Zealand Dollar', 'NZ$'], ['FJD', 'Fijian Dollar', 'FJ$'],
        ],
    ];

    $list = [];
    foreach ($raw as $region => $items) {
        foreach ($items as [$code, $name, $symbol]) {
            $list[$region][$code] = ['name' => $name, 'symbol' => $symbol];
        }
    }
    return $list;
}

// Every currency as CODE => ['name' => ..., 'symbol' => ...].
function currency_lookup(): array
{
    static $flat = null;
    if ($flat === null) {
        $flat = [];
        foreach (supported_currencies() as $items) {
            foreach ($items as $code => $info) $flat[$code] = $info;
        }
    }
    return $flat;
}

function is_supported_currency(string $code): bool
{
    return isset(currency_lookup()[strtoupper($code)]);
}

// Returns a valid currency code, or the default when the value is not in the list.
function normalize_currency(?string $code, string $default = 'ZAR'): string
{
    $code = strtoupper(trim((string) $code));
    return is_supported_currency($code) ? $code : $default;
}

// Builds the <optgroup>/<option> HTML for a currency drop-down. A saved code that is not in the
// list (for example from an older version) is kept as an extra option so it is never lost.
function currency_select_options(string $selected = 'ZAR'): string
{
    $selected = strtoupper(trim($selected)) ?: 'ZAR';
    $html = '';
    if (!is_supported_currency($selected) && preg_match('/^[A-Z]{3}$/', $selected)) {
        $html .= '<option value="' . e($selected) . '" selected>' . e($selected) . '</option>';
    }
    foreach (supported_currencies() as $region => $items) {
        $html .= '<optgroup label="' . e($region) . '">';
        foreach ($items as $code => $info) {
            $html .= '<option value="' . e($code) . '"' . ($code === $selected ? ' selected' : '') . '>'
                . e($info['name'] . ' (' . $code . ' - ' . $info['symbol'] . ')') . '</option>';
        }
        $html .= '</optgroup>';
    }
    return $html;
}
