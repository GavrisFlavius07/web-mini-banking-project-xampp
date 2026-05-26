<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/../database/Database.php';

class BalanceController
{
  public function show(Request $request, Response $response, $args) {
    $conn = Database::instance();
    
    if (!is_numeric($args['id_account'])) {
      $response->getBody()->write(json_encode(['error' => 'Invalid account id', 'code' => 400]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    $id_account = intval($args['id_account']);

    $sql = "SELECT `c`.`name` `curr`, COALESCE(`t`.`balance_after`, 0) `balance`
      FROM `account` `a`
      JOIN `currency` `c` ON `a`.`id_currency` = `c`.`id`
      LEFT JOIN `transaction` `t` ON `a`.`id` = `t`.`id_account`
      WHERE `a`.`id` = ?
      ORDER BY `t`.`created_at` DESC, `t`.`id` DESC
      LIMIT 1;";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_account);
    if (!$stmt->execute()) {
      $response->getBody()->write(json_encode(['error' => 'Query error', 'code' => 400]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    $result = $stmt->get_result();

    $results = $result->fetch_all(MYSQLI_ASSOC);

    if (empty($results)) {
      $response->getBody()->write(json_encode(['error' => 'Transaction for given account id not found', 'code' => 404]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $currency = $results[0]['curr'];
    $balance = floatval($results[0]['balance']);

    $response->getBody()->write(json_encode([
      'id_account' => $id_account,
      'currency' => $currency,
      'balance' => $balance
    ]));
    return $response->withHeader("Content-type", "application/json")->withStatus(200);
  }

  public function convert_fiat(Request $request, Response $response, $args) {
    $params = $request->getQueryParams();
    $conn = Database::instance();
    
    if (!is_numeric($args['id_account'])) {
      $response->getBody()->write(json_encode(['error' => 'Invalid account id', 'code' => 400]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    $id_account = intval($args['id_account']);

    if (!isset($params['to'])) {
      $response->getBody()->write(json_encode(['error' => 'Invalid or not present param "to"', 'code' => 400]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    $to = $params['to'];

    $currencies = array_map(function ($x) {return $x[0];}, $conn->query("SELECT `name` FROM `currency`")->fetch_all());

    if (!in_array($to, $currencies)) {
      $response->getBody()->write(json_encode(['error' => 'Currency "to" not found', 'code' => 404]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $sql = "SELECT `c`.`name` `curr`, COALESCE(`t`.`balance_after`, 0) `balance`
      FROM `account` `a`
      JOIN `currency` `c` ON `a`.`id_currency` = `c`.`id`
      LEFT JOIN `transaction` `t` ON `a`.`id` = `t`.`id_account`
      WHERE `a`.`id` = ?
      ORDER BY `t`.`created_at` DESC, `t`.`id` DESC
      LIMIT 1;";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_account);
    if (!$stmt->execute()) {
      $response->getBody()->write(json_encode(['error' => 'Query error', 'code' => 400]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    $result = $stmt->get_result();

    $results = $result->fetch_all(MYSQLI_ASSOC);

    if (empty($results)) {
      $response->getBody()->write(json_encode(['error' => 'Transaction for given account id not found', 'code' => 404]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $from = $results[0]['curr'];
    $balance = floatval($results[0]['balance']);

    if ($from == $to) {
      $response->getBody()->write(json_encode([
        'id_account' => $id_account,
        'provider' => 'Frankfurter',
        'conversion_type' => 'fiat',
        'from_currency' => $from,
        'to_currency' => $to,
        'original_balance' => $balance,
        'converted_balance' => $balance,
        'rate' => 1.0,
        'date' => null
      ]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    $conversion = json_decode(file_get_contents("https://api.frankfurter.dev/v2/rates?base=$from&quotes=$to"), true)[0];
    $rate = $conversion['rate'];
    $date = $conversion['date'] ?? null;

    $converted = round($balance * $rate, 2);

    $response->getBody()->write(json_encode([
      'id_account' => $id_account,
      'provider' => 'Frankfurter',
      'conversion_type' => 'fiat',
      'from_currency' => $from,
      'to_currency' => $to,
      'original_balance' => $balance,
      'converted_balance' => $converted,
      'rate' => $rate,
      'date' => $date
    ]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  }

  // https://api.binance.com/api/v3/ticker/price?symbol=BTCUSD
  public function convert_crypto(Request $request, Response $response, $args) {
    $params = $request->getQueryParams();
    $conn = Database::instance();
    
    if (!is_numeric($args['id_account'])) {
      $response->getBody()->write(json_encode(['error' => 'Invalid account id', 'code' => 400]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    $id_account = intval($args['id_account']);

    if (!isset($params['to']) || !is_string($params['to']) || trim($params['to']) === '') {
      $response->getBody()->write(json_encode(['error' => 'Invalid or not present param "to"', 'code' => 400]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    $to = strtoupper(trim($params['to']));

    $sql = "SELECT `c`.`name` `curr`, COALESCE(`t`.`balance_after`, 0) `balance`
      FROM `account` `a`
      JOIN `currency` `c` ON `a`.`id_currency` = `c`.`id`
      LEFT JOIN `transaction` `t` ON `a`.`id` = `t`.`id_account`
      WHERE `a`.`id` = ?
      ORDER BY `t`.`created_at` DESC, `t`.`id` DESC
      LIMIT 1;";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_account);
    if (!$stmt->execute()) {
      $response->getBody()->write(json_encode(['error' => 'Query error', 'code' => 400]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    $result = $stmt->get_result();

    $results = $result->fetch_all(MYSQLI_ASSOC);

    if (empty($results)) {
      $response->getBody()->write(json_encode(['error' => 'Transaction for given account id not found', 'code' => 404]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $from = strtoupper($results[0]['curr']);
    $balance = floatval($results[0]['balance']);

    if ($from === $to) {
      $response->getBody()->write(json_encode([
        'id_account' => $id_account,
        'provider' => 'Binance',
        'conversion_type' => 'crypto',
        'from_currency' => $from,
        'to_currency' => $to,
        'original_balance' => $balance,
        'converted_balance' => $balance,
        'rate' => 1.0
      ]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    $rate = null;
    $provider = 'Binance';

    // Se la valuta di partenza è fiat, prima converto in USD con Frankfurter,
    // poi uso il prezzo crypto/USD da Binance.
    $fiatToUsd = null;
    if ($from !== 'BTC' && $from !== 'ETH' && $from !== 'BNB' && $from !== 'XRP' && $from !== 'DOGE' && $from !== 'ADA' && $from !== 'SOL' && $from !== 'USDT' && $from !== 'BUSD') {
      if ($from === 'USD') {
        $fiatToUsd = 1.0;
      } else {
        $fiatToUsd = $this->fetchFrankfurterRate($from, 'USD');
      }
    }

    $tokenPrice = $this->fetchBinancePrice("{$to}USDT");
    if ($tokenPrice === null) {
      $inverseTokenPrice = $this->fetchBinancePrice("USDT{$to}");
      if ($inverseTokenPrice !== null && floatval($inverseTokenPrice['price']) > 0) {
        $tokenPrice = ['price' => 1 / floatval($inverseTokenPrice['price'])];
      }
    }

    if ($tokenPrice !== null && isset($tokenPrice['price']) && floatval($tokenPrice['price']) > 0) {
      if ($fiatToUsd !== null) {
        $rate = ($fiatToUsd / floatval($tokenPrice['price']));
      } else {
        // crypto -> crypto via USDT cross
        $directPair = $this->fetchBinancePrice("$to$from");
        if ($directPair !== null && isset($directPair['price']) && floatval($directPair['price']) > 0) {
          $rate = 1 / floatval($directPair['price']);
        } else {
          $fromUsdt = $this->fetchBinancePrice("{$from}USDT");
          if ($fromUsdt !== null && floatval($fromUsdt['price']) > 0) {
            $rate = floatval($fromUsdt['price']) / floatval($tokenPrice['price']);
          } else {
            $fromUsdtInverse = $this->fetchBinancePrice("USDT{$from}");
            if ($fromUsdtInverse !== null && floatval($fromUsdtInverse['price']) > 0) {
              $rate = (1 / floatval($fromUsdtInverse['price'])) / floatval($tokenPrice['price']);
            }
          }
        }
      }
    }

    if ($rate === null || $rate <= 0) {
      $response->getBody()->write(json_encode(['error' => 'Could not fetch a valid crypto conversion rate from Binance', 'code' => 502]));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(502);
    }

    $converted = round($balance * $rate, 8);

    $response->getBody()->write(json_encode([
      'id_account' => $id_account,
      'provider' => $provider,
      'conversion_type' => 'crypto',
      'from_currency' => $from,
      'to_currency' => $to,
      'original_balance' => $balance,
      'converted_balance' => $converted,
      'rate' => $rate
    ]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  }

  private function fetchFrankfurterRate(string $from, string $to) {
    $from = strtoupper(preg_replace('/[^A-Z]/', '', $from));
    $to = strtoupper(preg_replace('/[^A-Z]/', '', $to));
    if ($from === '' || $to === '') {
      return null;
    }

    $url = "https://api.frankfurter.dev/v2/rates?base={$from}&quotes={$to}";
    $result = @file_get_contents($url);
    if ($result === false) {
      return null;
    }

    $data = json_decode($result, true);
    if (!is_array($data)) {
      return null;
    }

    if (isset($data['rates']) && isset($data['rates'][$to]) && floatval($data['rates'][$to]) > 0) {
      return floatval($data['rates'][$to]);
    }

    if (isset($data[0]['rate']) && floatval($data[0]['rate']) > 0) {
      return floatval($data[0]['rate']);
    }

    return null;
  }

  private function fetchBinancePrice(string $symbol) {
    $symbol = strtoupper(preg_replace('/[^A-Z0-9]/', '', $symbol));
    if ($symbol === '') {
      return null;
    }

    $url = "https://api.binance.com/api/v3/ticker/price?symbol={$symbol}";
    $result = @file_get_contents($url);
    if ($result === false) {
      return null;
    }

    $data = json_decode($result, true);
    if (!is_array($data) || isset($data['code'])) {
      return null;
    }

    return $data;
  }
}
