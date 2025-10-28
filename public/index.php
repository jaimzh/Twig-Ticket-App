<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

session_start();

$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader, [
  'auto_reload' => true,
  // 'cache' => __DIR__ . '/../var/cache',
]);

function render(Environment $twig, string $tpl, array $ctx = []): void {
  $ctx['flash'] = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);
  echo $twig->render($tpl, $ctx);
}

function redirect(string $to): never {
  header("Location: $to");
  exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* ---------- seed demo tickets once ---------- */
if (!isset($_SESSION['tickets'])) {
  $_SESSION['tickets'] = [
    ['id'=>'1','title'=>'Fix login bug','description'=>'Users are unable to login with their credentials','status'=>'open','priority'=>'high'],
    ['id'=>'2','title'=>'Update dashboard UI','description'=>'Refresh the dashboard with new design system','status'=>'in_progress','priority'=>'medium'],
    ['id'=>'3','title'=>'Add export feature','description'=>'Allow users to export reports as PDF','status'=>'closed','priority'=>'low']
  ];
}

/* ---------- landing ---------- */
if ($path === '/' || $path === '/home') {
  render($twig, 'landing.html.twig', ['title' => 'TicketFlow']);
  exit;
}

/* ---------- auth: signup (from previous step) ---------- */
// ... keep your existing /auth/signup code here

/* ---------- auth: login (from previous step) ---------- */
// on success set:
/// $_SESSION['user'] = $email; redirect('/dashboard');

/* ---------- auth: logout (from previous step) ---------- */
// POST /auth/logout destroys session user and redirects to login

/* ---------- dashboard (from previous step) ---------- */
// GET /dashboard requires $_SESSION['user'] and renders dashboard

/* ===================== TICKETS CRUD ===================== */

/* ---- guard helper ---- */
$requireLogin = function () {
  if (empty($_SESSION['user'])) redirect('/auth/login');
};

/* ---- list + create ---- */
if ($path === '/tickets') {
  $requireLogin();
  if ($method === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $status = $_POST['status'] ?? 'open';
    $description = trim($_POST['description'] ?? '');

    $errors = ['title'=>''];
    if ($title === '') $errors['title'] = 'Title is required';

    if ($errors['title'] !== '') {
      render($twig, 'tickets/index.html.twig', [
        'title' => 'Tickets',
        'tickets' => $_SESSION['tickets'],
        'form' => compact('title','priority','status','description'),
        'errors' => $errors
      ]);
      exit;
    }

    $new = [
      'id' => (string) (time() . rand(100,999)),
      'title' => $title,
      'description' => $description,
      'status' => in_array($status, ['open','in_progress','closed']) ? $status : 'open',
      'priority' => in_array($priority, ['low','medium','high']) ? $priority : 'medium',
    ];
    array_unshift($_SESSION['tickets'], $new);
    $_SESSION['flash'] = ['type'=>'success','text'=>'Ticket created successfully!'];
    redirect('/tickets');
  }

  render($twig, 'tickets/index.html.twig', [
    'title' => 'Tickets',
    'tickets' => $_SESSION['tickets'],
    'errors' => []
  ]);
  exit;
}

/* ---- update ---- */
if ($method === 'POST' && preg_match('#^/tickets/(\d+)/update$#', $path, $m)) {
  $requireLogin();
  $id = $m[1];
  $title = trim($_POST['title'] ?? '');
  $status = $_POST['status'] ?? 'open';
  $priority = $_POST['priority'] ?? 'medium';
  $description = trim($_POST['description'] ?? '');

  if ($title === '') {
    $_SESSION['flash'] = ['type'=>'error','text'=>'Title is required'];
    redirect('/tickets');
  }

  foreach ($_SESSION['tickets'] as &$t) {
    if ($t['id'] === $id) {
      $t['title'] = $title;
      $t['status'] = in_array($status, ['open','in_progress','closed']) ? $status : $t['status'];
      $t['priority'] = in_array($priority, ['low','medium','high']) ? $priority : $t['priority'];
      $t['description'] = $description;
      break;
    }
  }
  unset($t);
  $_SESSION['flash'] = ['type'=>'success','text'=>'Ticket updated successfully!'];
  redirect('/tickets');
}

/* ---- delete ---- */
if ($method === 'POST' && preg_match('#^/tickets/(\d+)/delete$#', $path, $m)) {
  $requireLogin();
  $id = $m[1];
  $_SESSION['tickets'] = array_values(array_filter($_SESSION['tickets'], fn($t) => $t['id'] !== $id));
  $_SESSION['flash'] = ['type'=>'success','text'=>'Ticket deleted successfully!'];
  redirect('/tickets');
}

/* ---------- 404 fallback ---------- */
http_response_code(404);
render($twig, '404.html.twig', ['title'=>'Not Found']);
