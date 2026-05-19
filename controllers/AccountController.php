<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/../database/Database.php';

class AccountController{
  private function json(Response $response, array $payload, int $status = 200): Response {
    $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES));
    return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
  }

  public function accounts(Request $request, Response $response, $args){
    try {
      $conn = Database::instance();
      $sql = "SELECT `a`.`id`, `a`.`tax_id`, `a`.`owner_name`, `a`.`created_at`, `c`.`name` AS `currency`
        FROM `account` `a`
        JOIN `currency` `c` ON `a`.`id_currency` = `c`.`id`
        ORDER BY `a`.`id`";
      $result = $conn->query($sql);

      return $this->json($response, $result->fetch_all(MYSQLI_ASSOC));
    } catch (Throwable $e) {
      return $this->json($response, ['error' => 'Database error', 'code' => 500], 500);
    }
  }

  public function currencies(Request $request, Response $response, $args){
    try {
      $conn = Database::instance();
      $result = $conn->query("SELECT `id`, `name` FROM `currency` ORDER BY `name`");

      return $this->json($response, $result->fetch_all(MYSQLI_ASSOC));
    } catch (Throwable $e) {
      return $this->json($response, ['error' => 'Database error', 'code' => 500], 500);
    }
  }

  public function transactions(Request $request, Response $response, $args){
    try {
      $conn = Database::instance();
      $result = $conn->query("SELECT `id`, `id_account`, `type`, `amount`, `description`, `created_at`, `balance_after`
        FROM `transaction`
        ORDER BY `id_account`, `created_at`, `id`");

      return $this->json($response, $result->fetch_all(MYSQLI_ASSOC));
    } catch (Throwable $e) {
      return $this->json($response, ['error' => 'Database error', 'code' => 500], 500);
    }
  }
}
