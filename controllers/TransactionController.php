<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/../database/Database.php';

class TransactionController
{
  private function json(Response $response, array $payload, int $status = 200): Response {
    $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES));
    return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
  }

  private function parsePositiveId($value): ?int {
    if (!ctype_digit((string) $value) || (int) $value < 1) {
      return null;
    }

    return (int) $value;
  }

  private function getJsonBody(Request $request): array {
    $body = $request->getParsedBody();
    if (is_array($body)) {
      return $body;
    }

    $raw = (string) $request->getBody();
    if ($raw === '') {
      return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
  }

  private function accountExists(mysqli $conn, int $id_account): bool {
    $stmt = $conn->prepare("SELECT `id` FROM `account` WHERE `id` = ?");
    $stmt->bind_param('i', $id_account);
    $stmt->execute();

    return $stmt->get_result()->num_rows === 1;
  }

  private function latestTransaction(mysqli $conn, int $id_account, bool $lock = false): ?array {
    $sql = "SELECT `id`, `id_account`, `type`, `amount`, `description`, `created_at`, `balance_after`
      FROM `transaction`
      WHERE `id_account` = ?
      ORDER BY `created_at` DESC, `id` DESC
      LIMIT 1";
    if ($lock) {
      $sql .= " FOR UPDATE";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_account);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc() ?: null;
  }

  private function transactionById(mysqli $conn, int $id_account, int $id): ?array {
    $stmt = $conn->prepare("SELECT `id`, `id_account`, `type`, `amount`, `description`, `created_at`, `balance_after`
      FROM `transaction`
      WHERE `id_account` = ? AND `id` = ?");
    $stmt->bind_param('ii', $id_account, $id);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc() ?: null;
  }

  private function validateAccountArg(Response $response, array $args): array {
    $id_account = $this->parsePositiveId($args['id_account'] ?? null);
    if ($id_account === null) {
      return [null, $this->json($response, ['error' => 'Invalid account id', 'code' => 400], 400)];
    }

    return [$id_account, null];
  }

  private function validateTransactionArgs(Response $response, array $args): array {
    [$id_account, $error] = $this->validateAccountArg($response, $args);
    if ($error !== null) {
      return [null, null, $error];
    }

    $id = $this->parsePositiveId($args['id'] ?? null);
    if ($id === null) {
      return [null, null, $this->json($response, ['error' => 'Invalid transaction id', 'code' => 400], 400)];
    }

    return [$id_account, $id, null];
  }

  private function parseAmount(Request $request): array {
    $body = $this->getJsonBody($request);
    if (!isset($body['amount']) || !is_numeric($body['amount']) || (float) $body['amount'] <= 0) {
      return [null, null, 'Invalid amount'];
    }

    $description = trim((string) ($body['description'] ?? ''));

    return [round((float) $body['amount'], 2), $description, null];
  }

  private function createTransaction(Request $request, Response $response, array $args, string $type): Response {
    [$id_account, $error] = $this->validateAccountArg($response, $args);
    if ($error !== null) {
      return $error;
    }

    [$amount, $description, $amountError] = $this->parseAmount($request);
    if ($amountError !== null) {
      return $this->json($response, ['error' => $amountError, 'code' => 400], 400);
    }

    try {
      $conn = Database::instance();
      $conn->begin_transaction();

      if (!$this->accountExists($conn, $id_account)) {
        $conn->rollback();
        return $this->json($response, ['error' => 'Account not found', 'code' => 404], 404);
      }

      $latest = $this->latestTransaction($conn, $id_account, true);
      $currentBalance = $latest === null ? 0.0 : (float) $latest['balance_after'];
      $balanceAfter = $type === 'DEPOSIT' ? $currentBalance + $amount : $currentBalance - $amount;

      if ($balanceAfter < 0) {
        $conn->rollback();
        return $this->json($response, ['error' => 'Insufficient funds', 'code' => 400], 400);
      }

      $stmt = $conn->prepare("INSERT INTO `transaction` (`id_account`, `type`, `amount`, `description`, `balance_after`)
        VALUES (?, ?, ?, ?, ?)");
      $stmt->bind_param('isdsd', $id_account, $type, $amount, $description, $balanceAfter);
      $stmt->execute();

      $created = $this->transactionById($conn, $id_account, $conn->insert_id);
      $conn->commit();

      return $this->json($response, $created, 201);
    } catch (Throwable $e) {
      if (isset($conn)) {
        $conn->rollback();
      }

      return $this->json($response, ['error' => 'Database error', 'code' => 500], 500);
    }
  }

  public function showLogs(Request $request, Response $response, $args): Response {
    [$id_account, $error] = $this->validateAccountArg($response, $args);
    if ($error !== null) {
      return $error;
    }

    try {
      $conn = Database::instance();
      if (!$this->accountExists($conn, $id_account)) {
        return $this->json($response, ['error' => 'Account not found', 'code' => 404], 404);
      }

      $stmt = $conn->prepare("SELECT `id`, `id_account`, `type`, `amount`, `description`, `created_at`, `balance_after`
        FROM `transaction`
        WHERE `id_account` = ?
        ORDER BY `created_at`, `id`");
      $stmt->bind_param('i', $id_account);
      $stmt->execute();

      return $this->json($response, $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    } catch (Throwable $e) {
      return $this->json($response, ['error' => 'Database error', 'code' => 500], 500);
    }
  }

  public function showTrasaction(Request $request, Response $response, $args): Response {
    [$id_account, $id, $error] = $this->validateTransactionArgs($response, $args);
    if ($error !== null) {
      return $error;
    }

    try {
      $conn = Database::instance();
      $transaction = $this->transactionById($conn, $id_account, $id);
      if ($transaction === null) {
        return $this->json($response, ['error' => 'Transaction not found', 'code' => 404], 404);
      }

      return $this->json($response, $transaction);
    } catch (Throwable $e) {
      return $this->json($response, ['error' => 'Database error', 'code' => 500], 500);
    }
  }

  public function deposit(Request $request, Response $response, $args): Response {
    return $this->createTransaction($request, $response, $args, 'DEPOSIT');
  }

  public function withdraw(Request $request, Response $response, $args): Response {
    return $this->createTransaction($request, $response, $args, 'WITHDRAWAL');
  }

  public function editDescription(Request $request, Response $response, $args): Response {
    [$id_account, $id, $error] = $this->validateTransactionArgs($response, $args);
    if ($error !== null) {
      return $error;
    }

    $body = $this->getJsonBody($request);
    if (!array_key_exists('description', $body)) {
      return $this->json($response, ['error' => 'Missing description', 'code' => 400], 400);
    }

    $description = trim((string) $body['description']);

    try {
      $conn = Database::instance();
      $stmt = $conn->prepare("UPDATE `transaction`
        SET `description` = ?
        WHERE `id_account` = ? AND `id` = ?");
      $stmt->bind_param('sii', $description, $id_account, $id);
      $stmt->execute();

      if ($stmt->affected_rows === 0 && $this->transactionById($conn, $id_account, $id) === null) {
        return $this->json($response, ['error' => 'Transaction not found', 'code' => 404], 404);
      }

      return $this->json($response, $this->transactionById($conn, $id_account, $id));
    } catch (Throwable $e) {
      return $this->json($response, ['error' => 'Database error', 'code' => 500], 500);
    }
  }

  public function deleteLast(Request $request, Response $response, $args): Response {
    [$id_account, $id, $error] = $this->validateTransactionArgs($response, $args);
    if ($error !== null) {
      return $error;
    }

    try {
      $conn = Database::instance();
      $conn->begin_transaction();

      $transaction = $this->transactionById($conn, $id_account, $id);
      if ($transaction === null) {
        $conn->rollback();
        return $this->json($response, ['error' => 'Transaction not found', 'code' => 404], 404);
      }

      $latest = $this->latestTransaction($conn, $id_account, true);
      if ($latest === null || (int) $latest['id'] !== $id) {
        $conn->rollback();
        return $this->json($response, ['error' => 'Only the latest transaction can be deleted', 'code' => 409], 409);
      }

      $stmt = $conn->prepare("DELETE FROM `transaction` WHERE `id_account` = ? AND `id` = ?");
      $stmt->bind_param('ii', $id_account, $id);
      $stmt->execute();
      $conn->commit();

      return $this->json($response, ['deleted' => true, 'transaction' => $transaction]);
    } catch (Throwable $e) {
      if (isset($conn)) {
        $conn->rollback();
      }

      return $this->json($response, ['error' => 'Database error', 'code' => 500], 500);
    }
  }
}
