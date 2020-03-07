<?php

namespace ccxtpro;

// PLEASE DO NOT EDIT THIS FILE, IT IS GENERATED AND WILL BE OVERWRITTEN:
// https://github.com/ccxt/ccxt/blob/master/CONTRIBUTING.md#how-to-contribute-code

use Exception; // a common import
use \ccxt\ExchangeError;

class huobipro extends \ccxt\huobipro {

    use ClientTrait;

    public function describe () {
        return array_replace_recursive(parent::describe (), array(
            'has' => array(
                'ws' => true,
                'watchOrderBook' => true,
                'watchTickers' => false, // for now
                'watchTicker' => true,
                'watchTrades' => true,
                'watchBalance' => false, // for now
                'watchOHLCV' => true,
            ),
            'urls' => array(
                'api' => array(
                    'ws' => array(
                        'api' => array(
                            'public' => 'wss://{hostname}/ws',
                            'private' => 'wss://{hostname}/ws/v2',
                        ),
                        // these settings work faster for clients hosted on AWS
                        'api-aws' => array(
                            'public' => 'wss://api-aws.huobi.pro/ws',
                            'private' => 'wss://api-aws.huobi.pro/ws/v2',
                        ),
                    ),
                ),
            ),
            'options' => array(
                'tradesLimit' => 1000,
                'OHLCVLimit' => 1000,
                'ws' => array(
                    'api' => 'api', // or api-aws for clients hosted on AWS
                    'gunzip' => true,
                ),
            ),
        ));
    }

    public function watch_ticker ($symbol, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        // only supports a limit of 150 at this time
        $messageHash = 'market.' . $market['id'] . '.detail';
        $options = $this->safe_value($this->options, 'ws', array());
        $api = $this->safe_string($options, 'api', 'api');
        $url = $this->urls['api']['ws'][$api]['public'];
        $url = $this->implode_params($url, array( 'hostname' => $this->hostname ));
        $requestId = (string) $this->milliseconds ();
        $request = array(
            'sub' => $messageHash,
            'id' => $requestId,
        );
        $subscription = array(
            'id' => $requestId,
            'messageHash' => $messageHash,
            'symbol' => $symbol,
            'params' => $params,
        );
        return $this->watch ($url, $messageHash, array_merge($request, $params), $messageHash, $subscription);
    }

    public function handle_ticker ($client, $message) {
        //
        //     {
        //         $ch => 'market.btcusdt.detail',
        //         ts => 1583494163784,
        //         $tick => {
        //             id => 209988464418,
        //             low => 8988,
        //             high => 9155.41,
        //             open => 9078.91,
        //             close => 9136.46,
        //             vol => 237813910.5928412,
        //             amount => 26184.202558551195,
        //             version => 209988464418,
        //             count => 265673
        //         }
        //     }
        //
        $tick = $this->safe_value($message, 'tick', array());
        $ch = $this->safe_string($message, 'ch');
        $parts = explode('.', $ch);
        $marketId = $this->safe_string($parts, 1);
        if (is_array($this->markets_by_id) && array_key_exists($marketId, $this->markets_by_id)) {
            $market = $this->markets_by_id[$marketId];
            $ticker = $this->parse_ticker($tick, $market);
            $timestamp = $this->safe_value($message, 'ts');
            $ticker['timestamp'] = $timestamp;
            $ticker['datetime'] = $this->iso8601 ($timestamp);
            $symbol = $ticker['symbol'];
            $this->tickers[$symbol] = $ticker;
            $client->resolve ($ticker, $ch);
        }
        return $message;
    }

    public function watch_trades ($symbol, $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        // only supports a $limit of 150 at this time
        $messageHash = 'market.' . $market['id'] . '.trade.detail';
        $options = $this->safe_value($this->options, 'ws', array());
        $api = $this->safe_string($options, 'api', 'api');
        $url = $this->urls['api']['ws'][$api]['public'];
        $url = $this->implode_params($url, array( 'hostname' => $this->hostname ));
        $requestId = (string) $this->milliseconds ();
        $request = array(
            'sub' => $messageHash,
            'id' => $requestId,
        );
        $subscription = array(
            'id' => $requestId,
            'messageHash' => $messageHash,
            'symbol' => $symbol,
            'params' => $params,
        );
        $future = $this->watch ($url, $messageHash, array_merge($request, $params), $messageHash, $subscription);
        return $this->after ($future, $this->filterBySinceLimit, $since, $limit);
    }

    public function handle_trades ($client, $message) {
        //
        //     {
        //         $ch => "$market->btcusdt.trade.detail",
        //         ts => 1583495834011,
        //         $tick => {
        //             id => 105004645372,
        //             ts => 1583495833751,
        //             $data => $array(
        //                 {
        //                     id => 1.050046453727319e+22,
        //                     ts => 1583495833751,
        //                     tradeId => 102090727790,
        //                     amount => 0.003893,
        //                     price => 9150.01,
        //                     direction => "sell"
        //                 }
        //             )
        //         }
        //     }
        //
        $tick = $this->safe_value($message, 'tick', $array());
        $data = $this->safe_value($tick, 'data', $array());
        $ch = $this->safe_string($message, 'ch');
        $parts = explode('.', $ch);
        $marketId = $this->safe_string($parts, 1);
        if (is_array($this->markets_by_id) && array_key_exists($marketId, $this->markets_by_id)) {
            $market = $this->markets_by_id[$marketId];
            $symbol = $market['symbol'];
            $array = $this->safe_value($this->trades, $symbol, $array());
            for ($i = 0; $i < count($data); $i++) {
                $trade = $this->parse_trade($data[$i], $market);
                $array[] = $trade;
                $length = is_array($array) ? count($array) : 0;
                if ($length > $this->options['tradesLimit']) {
                    array_shift($array);
                }
                $this->trades[$symbol] = $array;
            }
            $client->resolve ($array, $ch);
        }
        return $message;
    }

    public function watch_ohlcv ($symbol, $timeframe = '1m', $since = null, $limit = null, $params = array ()) {
        $this->load_markets();
        $market = $this->market ($symbol);
        $interval = $this->timeframes[$timeframe];
        $messageHash = 'market.' . $market['id'] . '.kline.' . $interval;
        $options = $this->safe_value($this->options, 'ws', array());
        $api = $this->safe_string($options, 'api', 'api');
        $url = $this->urls['api']['ws'][$api]['public'];
        $url = $this->implode_params($url, array( 'hostname' => $this->hostname ));
        $requestId = (string) $this->milliseconds ();
        $request = array(
            'sub' => $messageHash,
            'id' => $requestId,
        );
        $subscription = array(
            'id' => $requestId,
            'messageHash' => $messageHash,
            'symbol' => $symbol,
            'timeframe' => $timeframe,
            'params' => $params,
        );
        $future = $this->watch ($url, $messageHash, array_merge($request, $params), $messageHash, $subscription);
        return $this->after ($future, $this->filterBySinceLimit, $since, $limit);
    }

    public function find_timeframe ($timeframe) {
        // redo to use reverse lookups in a static map instead
        $keys = is_array($this->timeframes) ? array_keys($this->timeframes) : array();
        for ($i = 0; $i < count($keys); $i++) {
            $key = $keys[$i];
            if ($this->timeframes[$key] === $timeframe) {
                return $key;
            }
        }
        return null;
    }

    public function handle_ohlcv ($client, $message) {
        //
        //     {
        //         $ch => 'market.btcusdt.kline.1min',
        //         ts => 1583501786794,
        //         $tick => {
        //             id => 1583501760,
        //             open => 9094.5,
        //             close => 9094.51,
        //             low => 9094.5,
        //             high => 9094.51,
        //             amount => 0.44639786263800907,
        //             vol => 4059.76919054,
        //             count => 16
        //         }
        //     }
        //
        $ch = $this->safe_string($message, 'ch');
        $parts = explode('.', $ch);
        $marketId = $this->safe_string($parts, 1);
        if (is_array($this->markets_by_id) && array_key_exists($marketId, $this->markets_by_id)) {
            $market = $this->markets_by_id[$marketId];
            $symbol = $market['symbol'];
            $interval = $this->safe_string($parts, 3);
            $timeframe = $this->find_timeframe ($interval);
            $this->ohlcvs[$symbol] = $this->safe_value($this->ohlcvs, $symbol, array());
            $stored = $this->safe_value($this->ohlcvs[$symbol], $timeframe, array());
            $tick = $this->safe_value($message, 'tick');
            $parsed = $this->parse_ohlcv($tick, $market, $timeframe, null, null);
            $length = is_array($stored) ? count($stored) : 0;
            if ($length && $parsed[0] === $stored[$length - 1][0]) {
                $stored[$length - 1] = $parsed;
            } else {
                $stored[] = $parsed;
                $limit = $this->safe_integer($this->options, 'OHLCVLimit', 1000);
                if ($length >= $limit) {
                    array_shift($stored);
                }
            }
            $this->ohlcvs[$symbol][$timeframe] = $stored;
            $client->resolve ($stored, $ch);
        }
    }

    public function watch_order_book ($symbol, $limit = null, $params = array ()) {
        if (($limit !== null) && ($limit !== 150)) {
            throw ExchangeError ($this->id . ' watchOrderBook accepts $limit = 150 only');
        }
        $this->load_markets();
        $market = $this->market ($symbol);
        // only supports a $limit of 150 at this time
        $limit = ($limit === null) ? 150 : $limit;
        $messageHash = 'market.' . $market['id'] . '.mbp.' . (string) $limit;
        $options = $this->safe_value($this->options, 'ws', array());
        $api = $this->safe_string($options, 'api', 'api');
        $url = $this->urls['api']['ws'][$api]['public'];
        $url = $this->implode_params($url, array( 'hostname' => $this->hostname ));
        $requestId = (string) $this->milliseconds ();
        $request = array(
            'sub' => $messageHash,
            'id' => $requestId,
        );
        $subscription = array(
            'id' => $requestId,
            'messageHash' => $messageHash,
            'symbol' => $symbol,
            'limit' => $limit,
            'params' => $params,
            'method' => array($this, 'handle_order_book_subscription'),
        );
        $future = $this->watch ($url, $messageHash, array_merge($request, $params), $messageHash, $subscription);
        return $this->after ($future, array($this, 'limit_order_book'), $symbol, $limit, $params);
    }

    public function limit_order_book ($orderbook, $symbol, $limit = null, $params = array ()) {
        return $orderbook->limit ($limit);
    }

    public function handle_order_book_snapshot ($client, $message, $subscription) {
        //
        //     {
        //         id => 1583473663565,
        //         rep => 'market.btcusdt.mbp.150',
        //         status => 'ok',
        //         $data => {
        //             seqNum => 104999417756,
        //             bids => [
        //                 [9058.27, 0],
        //                 [9058.43, 0],
        //                 [9058.99, 0],
        //             ],
        //             asks => [
        //                 [9084.27, 0.2],
        //                 [9085.69, 0],
        //                 [9085.81, 0],
        //             ]
        //         }
        //     }
        //
        $symbol = $this->safe_string($subscription, 'symbol');
        $messageHash = $this->safe_string($subscription, 'messageHash');
        $orderbook = $this->orderbooks[$symbol];
        $data = $this->safe_value($message, 'data');
        $snapshot = $this->parse_order_book($data);
        $snapshot['nonce'] = $this->safe_integer($data, 'seqNum');
        $orderbook->reset ($snapshot);
        // unroll the accumulated deltas
        $messages = $orderbook->cache;
        for ($i = 0; $i < count($messages); $i++) {
            $message = $messages[$i];
            $this->handle_order_book_message ($client, $message, $orderbook);
        }
        $this->orderbooks[$symbol] = $orderbook;
        $client->resolve ($orderbook, $messageHash);
    }

    public function watch_order_book_snapshot ($client, $message, $subscription) {
        $symbol = $this->safe_string($subscription, 'symbol');
        $limit = $this->safe_integer($subscription, 'limit');
        $params = $this->safe_value($subscription, 'params');
        $messageHash = $this->safe_string($subscription, 'messageHash');
        $options = $this->safe_value($this->options, 'ws', array());
        $api = $this->safe_value($options, 'api');
        $url = $this->urls['api']['ws'][$api]['public'];
        $url = $this->implode_params($url, array( 'hostname' => $this->hostname ));
        $requestId = (string) $this->milliseconds ();
        $request = array(
            'req' => $messageHash,
            'id' => $requestId,
        );
        // this is a temporary $subscription by a specific $requestId
        // it has a very short lifetime until the snapshot is received over ws
        $snapshotSubscription = array(
            'id' => $requestId,
            'messageHash' => $messageHash,
            'symbol' => $symbol,
            'limit' => $limit,
            'params' => $params,
            'method' => array($this, 'handle_order_book_snapshot'),
        );
        $future = $this->watch ($url, $requestId, $request, $requestId, $snapshotSubscription);
        return $this->after ($future, array($this, 'limit_order_book'), $symbol, $limit, $params);
    }

    public function handle_delta ($bookside, $delta) {
        $price = $this->safe_float($delta, 0);
        $amount = $this->safe_float($delta, 1);
        $bookside->store ($price, $amount);
    }

    public function handle_deltas ($bookside, $deltas) {
        for ($i = 0; $i < count($deltas); $i++) {
            $this->handle_delta ($bookside, $deltas[$i]);
        }
    }

    public function handle_order_book_message ($client, $message, $orderbook) {
        //
        //     {
        //         ch => "market.btcusdt.mbp.150",
        //         ts => 1583472025885,
        //         $tick => {
        //             $seqNum => 104998984994,
        //             $prevSeqNum => 104998984977,
        //             $bids => [
        //                 [9058.27, 0],
        //                 [9058.43, 0],
        //                 [9058.99, 0],
        //             ],
        //             $asks => [
        //                 [9084.27, 0.2],
        //                 [9085.69, 0],
        //                 [9085.81, 0],
        //             ]
        //         }
        //     }
        //
        $tick = $this->safe_value($message, 'tick', array());
        $seqNum = $this->safe_integer($tick, 'seqNum');
        $prevSeqNum = $this->safe_integer($tick, 'prevSeqNum');
        if (($prevSeqNum <= $orderbook['nonce']) && ($seqNum > $orderbook['nonce'])) {
            $asks = $this->safe_value($tick, 'asks', array());
            $bids = $this->safe_value($tick, 'bids', array());
            $this->handle_deltas ($orderbook['asks'], $asks);
            $this->handle_deltas ($orderbook['bids'], $bids);
            $orderbook['nonce'] = $seqNum;
            $timestamp = $this->safe_integer($message, 'ts');
            $orderbook['timestamp'] = $timestamp;
            $orderbook['datetime'] = $this->iso8601 ($timestamp);
        }
        return $orderbook;
    }

    public function handle_order_book ($client, $message) {
        //
        // deltas
        //
        //     {
        //         $ch => "$market->btcusdt.mbp.150",
        //         ts => 1583472025885,
        //         tick => {
        //             seqNum => 104998984994,
        //             prevSeqNum => 104998984977,
        //             bids => [
        //                 [9058.27, 0],
        //                 [9058.43, 0],
        //                 [9058.99, 0],
        //             ],
        //             asks => [
        //                 [9084.27, 0.2],
        //                 [9085.69, 0],
        //                 [9085.81, 0],
        //             ]
        //         }
        //     }
        //
        $messageHash = $this->safe_string($message, 'ch');
        $ch = $this->safe_value($message, 'ch');
        $parts = explode('.', $ch);
        $marketId = $this->safe_string($parts, 1);
        $market = null;
        $symbol = null;
        if ($marketId !== null) {
            if (is_array($this->markets_by_id) && array_key_exists($marketId, $this->markets_by_id)) {
                $market = $this->markets_by_id[$marketId];
                $symbol = $market['symbol'];
            }
        }
        $orderbook = $this->orderbooks[$symbol];
        if ($orderbook['nonce'] === null) {
            $orderbook->cache[] = $message;
        } else {
            $this->handle_order_book_message ($client, $message, $orderbook);
            $client->resolve ($orderbook, $messageHash);
        }
    }

    public function sign_message ($client, $messageHash, $message, $params = array ()) {
        // todo => implement huobipro signMessage
        return $message;
    }

    public function handle_order_book_subscription ($client, $message, $subscription) {
        $symbol = $this->safe_string($subscription, 'symbol');
        $limit = $this->safe_integer($subscription, 'limit');
        if (is_array($this->orderbooks) && array_key_exists($symbol, $this->orderbooks)) {
            unset($this->orderbooks[$symbol]);
        }
        $this->orderbooks[$symbol] = $this->order_book (array(), $limit);
        // watch the snapshot in a separate async call
        $this->spawn (array($this, 'watch_order_book_snapshot'), $client, $message, $subscription);
    }

    public function handle_subscription_status ($client, $message) {
        //
        //     {
        //         "$id" => 1583414227,
        //         "status" => "ok",
        //         "subbed" => "market.btcusdt.mbp.150",
        //         "ts" => 1583414229143
        //     }
        //
        $id = $this->safe_string($message, 'id');
        $subscriptionsById = $this->index_by($client->subscriptions, 'id');
        $subscription = $this->safe_value($subscriptionsById, $id);
        if ($subscription !== null) {
            $method = $this->safe_value($subscription, 'method');
            if ($method !== null) {
                return $method($client, $message, $subscription);
            }
            // clean up
            if (is_array($client->subscriptions) && array_key_exists($id, $client->subscriptions)) {
                unset($client->subscriptions[$id]);
            }
        }
        return $message;
    }

    public function handle_system_status ($client, $message) {
        //
        // todo => answer the question whether handleSystemStatus should be renamed
        // and unified as handleStatus for any usage pattern that
        // involves system status and maintenance updates
        //
        //     {
        //         id => '1578090234088', // connectId
        //         type => 'welcome',
        //     }
        //
        return $message;
    }

    public function handle_subject ($client, $message) {
        //
        //     {
        //         $ch => "market.btcusdt.mbp.150",
        //         ts => 1583472025885,
        //         tick => {
        //             seqNum => 104998984994,
        //             prevSeqNum => 104998984977,
        //             bids => [
        //                 [9058.27, 0],
        //                 [9058.43, 0],
        //                 [9058.99, 0],
        //             ],
        //             asks => [
        //                 [9084.27, 0.2],
        //                 [9085.69, 0],
        //                 [9085.81, 0],
        //             ]
        //         }
        //     }
        //
        $ch = $this->safe_value($message, 'ch');
        $parts = explode('.', $ch);
        $type = $this->safe_string($parts, 0);
        if ($type === 'market') {
            $methodName = $this->safe_string($parts, 2);
            $methods = array(
                'mbp' => array($this, 'handle_order_book'),
                'detail' => array($this, 'handle_ticker'),
                'trade' => array($this, 'handle_trades'),
                'kline' => array($this, 'handle_ohlcv'),
                // ...
            );
            $method = $this->safe_value($methods, $methodName);
            if ($method === null) {
                return $message;
            } else {
                return $method($client, $message);
            }
        }
    }

    public function pong ($client, $message) {
        //
        //     array( ping => 1583491673714 )
        //
        $client->send (array( 'pong' => $this->safe_integer($message, 'ping') ));
    }

    public function handle_ping ($client, $message) {
        $this->spawn (array($this, 'pong'), $client, $message);
    }

    public function handle_error_message ($client, $message) {
        return $message;
    }

    public function handle_message ($client, $message) {
        if ($this->handle_error_message ($client, $message)) {
            //
            //     array("id":1583414227,"status":"ok","subbed":"market.btcusdt.mbp.150","ts":1583414229143)
            //
            if (is_array($message) && array_key_exists('id', $message)) {
                $this->handle_subscription_status ($client, $message);
            } else if (is_array($message) && array_key_exists('ch', $message)) {
                // route by channel aka topic aka subject
                $this->handle_subject ($client, $message);
            } else if (is_array($message) && array_key_exists('ping', $message)) {
                $this->handle_ping ($client, $message);
            }
        }
    }
}
