<?php

$root = dirname (dirname (dirname (__FILE__)));

include $root . '/ccxt.php';

date_default_timezone_set ('UTC');

$exchange = new \ccxt\coinbase(array(
    'apiKey' => 'YOUR_API_KEY',  // required for fetch_orders, fetch_my_trades
    'secret' => 'YOUR_SECRET',  // required for fetch_orders, fetch_my_trades
    'verbose' => true, // uncomment for debugging
    // https://github.com/ccxt/ccxt/wiki/Manual#rate-limit
    'enableRateLimit' => true, // rate-limiting is required by the Manual
));

$exchange->load_markets ();

// $exchange->verbose = true; // uncomment for debugging

$result = array();
$params = array();
$loop = true;
do {
    $balance = $exchange->fetch_balance($params);
    $pagination = $exchange->safe_value($balance['info'], 'pagination');
    if ($pagination === null) {
        $loop = false;
    } else {
        $next_starting_after = $exchange->safe_string ($pagination, 'next_starting_after');
        if ($next_starting_after !== null) {
            $params['starting_after'] = $next_starting_after;
        } else {
            $loop = false;
        }
    }
    echo $exchange->iso8601($exchange->milliseconds()) . "\n";
    $result = $exchange->deep_extend($result, $balance);
} while ($loop); // fetch up to 1000 results

echo "======================================================================\n";
var_dump($result);

?>