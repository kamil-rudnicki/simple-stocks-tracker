<?php

include 'config.php';

function getTransactionsFromDB(): array
{
  $dir = 'sqlite://Users/kamil/Documents/Stocks/Stocks.sqlite3';
  $dbh = new PDO($dir) or die("cannot open the database");
  $query = "SELECT * FROM transactions ORDER BY date";
  $transactions = [];
  foreach ($dbh->query($query) as $row) {
    $transaction['date'] = $row[1];
    $transaction['type'] = $row[2];
    $transaction['stock'] = $row[3];
    $transaction['price'] = $row[4];
    $transaction['quantity'] = $row[5];
    $transaction['commision'] = $row[6];
    $transaction['currency'] = $row[7];
    $transaction['system'] = $row[8];
    $transactions[] = $transaction;
  }
  $dbh = null;
  return $transactions;
}

function getPrice(string $stock, string $date): float
{
  $price = 100;

  $cache = json_decode(file_get_contents("cache.json"), true);

  if (isset($cache[$date]) && isset($cache[$date][$stock])) {
    return (float)$cache[$date][$stock];
  }

  if ($stock[0] !== '-') {
    $from = date("Y-m-d", time() - 24 * 3600 * 5);
    $json = file_get_contents("https://eodhistoricaldata.com/api/eod/$stock?api_token={$config['eodhistoricaldataApiToken']}&period=d&fmt=json&from=$from&order=d");
    if ($json !== '') {
      $data = json_decode($json, true);
      $price = $data[0]['close'];
    } else {
      echo $stock;
      print_r($json);
    }

  } else {
    echo "Update current price.";
    exit;
    if ($stock === '-WSE:LEG') $price = 40.0;
    else if ($stock === '-WSE:ETFSP500') $price = 179.94;
    else if ($stock === '-WSE:COLUMBUS') $price = 32.76;
    else if ($stock === '-WSE:B24') $price = 25.0;
    else {
      echo "Missing stock price for $stock.";
      exit;
    }
  }

  $cache[$date][$stock] = $price;

  $json = json_encode($cache);
  file_put_contents("cache.json", $json);

  return $price;
}

function getAssetsByTime(array $transactions): array
{
  $assets = [];

  foreach ($transactions as $transaction) {

    $event = [];
    $event['date'] = $transaction['date'];
    $event['stock'] = $transaction['stock'];
    $event['currency'] = $transaction['currency'];
    if (isset($assets[$transaction['stock']])) {
      $i = count($assets[$transaction['stock']]) - 1;
      if ($transaction['type'] === 'buy') {
        $event['quantity'] = $assets[$transaction['stock']][$i]['quantity'] + $transaction['quantity'];
        $event['cost'] = $assets[$transaction['stock']][$i]['cost'] + $transaction['quantity'] * $transaction['price'];
        $event['commision'] = $assets[$transaction['stock']][$i]['commision'] + $transaction['commision'];
      } else {
        $event['quantity'] = $assets[$transaction['stock']][$i]['quantity'] - $transaction['quantity'];
        $event['cost'] = $assets[$transaction['stock']][$i]['cost'];
      }
      $assets[$transaction['stock']][] = $event;
    } else {
      $event['quantity'] = $transaction['quantity'];
      $event['commision'] = $transaction['commision'];
      $event['cost'] = $transaction['quantity'] * $transaction['price'];
      $assets[$transaction['stock']][] = $event;
    }
  }

  return $assets;
}

function getCurrentPortfolio(array $transactions): array
{
  $assetsByTime = getAssetsByTime($transactions);
  $openAssets = [];
  foreach ($assetsByTime as $stock => $stockHistory) {
    $openAssets[$stock] = $stockHistory[count($stockHistory) - 1];
    $price = getPrice($openAssets[$stock]['stock'], date('Y-m-d'));
    $openAssets[$stock]['value'] = $price * $openAssets[$stock]['quantity'];
    $openAssets[$stock]['price'] = $price;
  }
  return $openAssets;
}

function formatCurrency(float $amount, string $currency = ''): string
{
  setlocale(LC_MONETARY, "pl_PL");
  return ($amount < 0 ? '-' : '') . money_format("%!n", $amount) . " " . $currency; //.0
}

function formatPercentage(float $percent): string
{
  return (string)number_format($percent * 100, 2, '.', '') . "%";
}

function USDtoPLN(float $usd): float {
    return $usd * getPrice('PLN.FOREX', date('Y-m-d'));
}

function friendlyCompanyName(string $ticker): string
{
  if ($ticker === 'AAPL.US') return 'Apple';
  if ($ticker === 'ALE.WAR') return 'Allegro';
  if ($ticker === 'WSE:B24') return 'Brand24';
  if ($ticker === 'WSE:COLUMBUS') return 'Columbus';
  if ($ticker === 'CDR.WAR') return 'CD Projekt';
  if ($ticker === 'WDAY.US') return 'Workday';
  if ($ticker === 'WSE:ETFSP500') return 'ETFS 500';
  if ($ticker === 'SNOW.US') return 'Snowflake';
  if ($ticker === 'DDOG.US') return 'DataDog';
  if ($ticker === 'ZM.US') return 'Zoom';
  if ($ticker === 'TWLO.US') return 'Twilio';
  return $ticker;
}

$transactions = getTransactionsFromDB();
$currentPortfolio = getCurrentPortfolio($transactions);

// echo "<pre>";
// print_r($currentPortfolio);
// print_r($transactions);
// echo "</pre>";
?>

<!doctype html>
<html class="no-js" lang="">

<head>
    <meta charset="utf-8">
    <title>Stocks Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-F3w7mX95PdgyTmZZMECAngseQB83DfGTowi0iMjiWaeVhAn4FJkqJByhZMI3AhiU" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-/bQdsTh/da6pkI1MST/rWKFNjaCP5gBSY4sEBT38Q/9RBh9AH40zEOg7Hlq2THRZ"
            crossorigin="anonymous"></script>
</head>

<body>

<div class="container">

    <img src="https://cdn-icons-png.flaticon.com/512/950/950610.png" style="width: 100px;"/>

    <br/><br/>
    <br/><br/>

    <h3>Current Portfolio</h3>
    <table class="table table-hover table-striped" style="text-align: right; font-family: monospace;">
        <thead>
        <tr>
            <th scope="col" style='text-align: left;'>Ticker</th>
            <th scope="col">Shares</th>
            <th scope="col">Current Price</th>
            <th scope="col">Current Value</th>
            <th scope="col">Orginal Value</th>
            <th scope="col">Value Change</th>
            <th scope="col">%</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $sum = [];
        $sumCost = [];

        foreach ($currentPortfolio as $stock) {
          if ($stock['quantity'] === 0) continue;

          $change = $stock['value'] - ($stock['cost']);

          if (isset($sum[$stock['currency']])) {
            $sum[$stock['currency']] += $stock['value'];
          } else {
            $sum[$stock['currency']] = $stock['value'];
          }

          if (isset($sumCost[$stock['currency']])) {
            $sumCost[$stock['currency']] += $stock['cost'];
          } else {
            $sumCost[$stock['currency']] = $stock['cost'];
          }

          echo "<tr>
          <th style='text-align: left;'>" . friendlyCompanyName(str_replace("-", "", $stock['stock'])) . "</th>
          <td>{$stock['quantity']}</td>
          <td>" . formatCurrency($stock['price'], $stock['currency']) . "</td>
          <td>" . formatCurrency($stock['value'], $stock['currency']) . "</td>
          <td>" . formatCurrency($stock['cost'], $stock['currency']) . "</td>
          <td style='color: " . ($change < 0 ? 'red' : 'green') . ";'>" . formatCurrency($change, $stock['currency']) . "</td>
          <td style='color: " . ($change < 0 ? 'red' : 'green') . ";'>" . formatPercentage($change / $stock['cost']) . "</td>
        </tr>";
        }
        ?>
        </tbody>
        <tfoot>
        <?php
        $totalPLN = 0;
        foreach ($sum as $currency => $total) {
            $plnText = '';
            $plnTextCurrentValue = '';
            if($currency === 'USD') {
                $plnText = '<br />'.formatCurrency(USDtoPLN($sum[$currency] - $sumCost[$currency]), 'PLN');
                $plnTextCurrentValue = '<br />'.formatCurrency(USDtoPLN($total), 'PLN');
                $totalPLN += USDtoPLN($sum[$currency] - $sumCost[$currency]);
            }
            if($currency === 'PLN') {
                $totalPLN += $sum[$currency] - $sumCost[$currency];
            }

            echo "<tr>
                   <td></td>
                   <td></td>
                  <td style='text-align: left;'></td>
                  <td>" . formatCurrency($total, $currency) . "$plnTextCurrentValue</td>
                  <td>" . formatCurrency($sumCost[$currency], $currency) . "</td>
                  <td style='color: " . ($sum[$currency] - $sumCost[$currency] < 0 ? 'red' : 'green') . ";'>" . formatCurrency($sum[$currency] - $sumCost[$currency], $currency) . "$plnText</td>
                  <td style='color: " . ($sum[$currency] - $sumCost[$currency] < 0 ? 'red' : 'green') . ";'>" . formatPercentage(($sum[$currency] - $sumCost[$currency]) / $sumCost[$currency]) . "</td>
                </tr>";
        }
        ?>
        <tr>
            <td style='text-align: left;'></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td style="color: green;"><?php echo formatCurrency($totalPLN, 'PLN'); ?></td>
            <td></td>
        </tr>
        </tfoot>
    </table>

    <h3 style="margin-top: 80px;">Closed Positions</h3>
    <table class="table table-hover table-striped" style="text-align: right; font-family: monospace;">
        <thead>
        <tr>
            <th scope="col">Date</th>
            <th scope="col">Shares</th>
            <th scope="col">Ticker</th>
            <th scope="col">Avg. Price Paid</th>
            <th scope="col">Avg. Current Price</th>
            <th scope="col">Commision</th>
            <th scope="col">Value Change</th>
            <th scope="col">%</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>2021-09-27</td>
            <td>4</td>
            <td>Asana</td>
            <td>300 USD</td>
            <td>350 USD</td>
            <td>10 USD</td>
            <td style='color: green;'>40 USD</td>
            <td style='color: green;'>13.33%</td>
        </tr>
        </tbody>
    </table>

    <div>
        <h5>Total</h5>
        <table style="text-align: right; font-family: monospace; width: auto !important;" class="table">
            <tr>
                <td>Total Value Change</td>
                <td>40 USD</td>
            </tr>
        </table>
    </div>

    <h3 style="margin-top: 40px;">Portfolio Realized</h3>
    <ul>
        <li style='color: green;'>2020: 3 400 PLN (+8.45%)</li>
        <li style='color: green;'>2021: 3 400 PLN (+8.45%)</li>
    </ul>

</div>

<br/><br/><br/><br/>

</body>

</html>
