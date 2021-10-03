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
    if($amount === 0.0) return '';
    setlocale(LC_MONETARY, "pl_PL");
    return ($amount < 0 ? '-' : '') . money_format("%!n", $amount) . " " . $currency; //.0
}

function formatPercentage(float $percent): string
{
    if($percent === 0.0) {
        return '';
    }
    return (string)number_format($percent * 100, 2, '.', '') . "%";
}

function USDtoPLN(float $usd): float {
    return $usd * getPrice('PLN.FOREX', date('Y-m-d'));
}

function friendlyCompanyName(string $ticker): string
{
    if($ticker[0] === '-') $ticker = substr($ticker, 1);

    if (stripos($ticker, 'AAPL') !== false) return 'US: Apple';
    if (stripos($ticker, 'ALE') !== false) return 'WSE: Allegro';
    if (stripos($ticker, 'B24') !== false) return 'WSE: Brand24';
    if (stripos($ticker, 'COLUMBUS') !== false) return 'WSE: Columbus';
    if (stripos($ticker, 'CDR') !== false) return 'WSE: CD Projekt';
    if (stripos($ticker, '500') !== false) return 'WSE: ETFS 500';
    if (stripos($ticker, 'ZM') !== false) return 'US: Zoom';
    if (stripos($ticker, 'TWLO') !== false) return 'US: Twilio';
    if (stripos($ticker, 'ASAN') !== false) return 'US: Asana';
    if (stripos($ticker, 'WDAY') !== false) return 'US: Workday';
    if (stripos($ticker, 'LEG') !== false) return 'WSE: Legimi';
    if (stripos($ticker, 'SNOW') !== false) return 'US: Snowflake';
    if (stripos($ticker, 'PLN') !== false) return 'FOREX: PLN-USD';
    if (stripos($ticker, 'ETH') !== false) return 'CRYPTO: Ethereum';
    if (stripos($ticker, 'DDOG') !== false) return 'US: Datadog';

    return $ticker;
}

function getAvgBuyPrice(string $ticker, string $date, array $transactions): float {
    $totalShares = 0;
    $totalAmount = 0;
    foreach($transactions as $transaction) {
        if($transaction['stock'] === $ticker && $transaction['date'] <= $date && $transaction['type'] === 'buy') {
            $totalShares += $transaction['quantity'];
            $totalAmount += $transaction['price'] * $transaction['quantity'];
        }
    }
    return $totalAmount / $totalShares;
}

$transactions = getTransactionsFromDB();
$currentPortfolio = getCurrentPortfolio($transactions);
$currencies = ['USD', 'PLN'];

?>

<!doctype html>
<html class="no-js" lang="">

<head>
    <meta charset="utf-8">
    <title>Stocks Dashboard</title>
    <link rel="icon" type="image/png" href="https://cdn-icons-png.flaticon.com/512/950/950610.png" />
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

    <h3 style="margin-top: 80px;">Transactions</h3>
    <table class="table table-hover table-striped" style="text-align: right; font-family: monospace;">
        <thead>
        <tr>
            <th scope="col" style='text-align: left;'>Date</th>
            <th scope="col" style='text-align: left;'>Type</th>
            <th scope="col" style='text-align: left;'>Ticker</th>
            <th scope="col">Shares</th>
            <th scope="col">Avg. Price</th>
            <th scope="col">Amount</th>
            <th scope="col">Commision</th>
            <th scope="col">Value Change</th>
            <th scope="col">%</th>
        </tr>
        </thead>
        <tbody>
        <?php

        $totals = [];
        foreach ($transactions as $i => $transaction) {
            $transactions[$i]['avgBuyPrice'] = 0.0;
            $transactions[$i]['change'] = 0.0;
            $transactions[$i]['changePercentage'] = 0.0;

            if($transaction['type'] === 'sell') {
                $transactions[$i]['avgBuyPrice'] = getAvgBuyPrice($transaction['stock'], $transaction['date'], $transactions);
                $transactions[$i]['change'] = ($transaction['price'] - $transactions[$i]['avgBuyPrice']) * $transaction['quantity'];
                $transactions[$i]['changePercentage'] = ($transaction['price'] - $transactions[$i]['avgBuyPrice']) / $transactions[$i]['avgBuyPrice'];
            }

            $totals['commission'][$transaction['currency']] += $transaction['commision'];
            $totals['change'][$transaction['currency']] += $transactions[$i]['change'];
        }

        foreach ($transactions as $transaction) {
            echo "<tr>
                <td style='text-align: left;'>{$transaction['date']}</td>
                <td style='text-align: left;'>{$transaction['type']}</td>
                <td style='text-align: left;'>".friendlyCompanyName($transaction['stock'])."</td>
                <td>{$transaction['quantity']}</td>
                <td>".formatCurrency($transaction['price'], $transaction['currency'])."</td>
                <td>".formatCurrency($transaction['price'] * $transaction['quantity'], $transaction['currency'])."</td>
                <td style='color: red;'>".formatCurrency($transaction['commision'], $transaction['currency'])."</td>
                <td style='color: " . ($transaction['change'] < 0 ? 'red' : 'green') . ";'>".formatCurrency($transaction['change'], $transaction['currency'])."</td>
                <td style='color: " . ($transaction['change'] < 0 ? 'red' : 'green') . ";'>".formatPercentage($transaction['changePercentage'])."</td>
            </tr>";
        }

        ?>
        </tbody>
        <tfoot>
            <tr>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td style="color: red;">
                <?php
                foreach($currencies as $currency) {
                    echo formatCurrency($totals['commission'][$currency], $currency).'<br />';
                }
                ?>
                </td>
                <td style="color: green;">
                <?php
                foreach($currencies as $currency) {
                    echo formatCurrency($totals['change'][$currency], $currency).'<br />';
                }
                ?>
                </td>
                <td></td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td style="color: red;">
                    <?php
                    echo formatCurrency($totals['commission']['PLN'] + USDtoPLN($totals['commission']['USD']), 'PLN').'<br />';
                    ?>
                </td>
                <td style="color: green;">
                    <?php
                    echo formatCurrency($totals['change']['PLN'] + USDtoPLN($totals['change']['USD']), 'PLN').'<br />';
                    echo formatCurrency($totals['change']['PLN'] + USDtoPLN($totals['change']['USD']) - ($totals['commission']['PLN'] + USDtoPLN($totals['commission']['USD'])), 'PLN').'<br />';
                    ?>
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>

</div>

<br/><br/><br/><br/>
<br/><br/><br/><br/>

</body>

</html>
