<?php
use Slim\Factory\AppFactory;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/controllers/AccountController.php';
require __DIR__ . '/controllers/BalanceController.php';
require __DIR__ . '/controllers/TransactionController.php';

$app = AppFactory::create();

$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

$accountController = new AccountController();
$balanceController = new BalanceController();
$transactionController = new TransactionController();

$app->get('/account', [$accountController, 'accounts']);
$app->get('/currency', [$accountController, 'currencies']);
$app->get('/trans', [$accountController, 'transactions']);

$app->get('/account/{id_account}/balance', [$balanceController, 'show']);
$app->get('/account/{id_account}/balance/convert/fiat', [$balanceController, 'convert_fiat']);
$app->get('/account/{id_account}/balance/convert/crypto', [$balanceController, 'convert_crypto']);

$app->get('/account/{id_account}/transaction', [$transactionController, 'showLogs']);
$app->get('/account/{id_account}/transaction/{id}', [$transactionController, 'showTrasaction']);
$app->post('/account/{id_account}/deposit', [$transactionController, 'deposit']);
$app->post('/account/{id_account}/withdrawal', [$transactionController, 'withdraw']);
$app->put('/account/{id_account}/transaction/{id}', [$transactionController, 'editDescription']);
$app->delete('/account/{id_account}/transaction/{id}', [$transactionController, 'deleteLast']);

$app->run();
