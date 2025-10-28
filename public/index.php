<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

session_start();

/* -------------------- Twig -------------------- */
$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader, [
  'auto_reload' => true,
  // For production later:
  // 'cache' => __DIR__ . '/../var/cache',
]);

/* -------------------- Helpers -------------------- */
function render(Environment $twig, string $tpl, array $ctx = []): void {
  // flash messages
  $ctx['flash'] = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);

  // expose auth state to all templates if you care
  $ctx['auth_user'] = $_SESSION['user'] ?? null;

  echo $twig->render($tpl, $ctx);
}

function redirect(string $to): never {
  header("Location: $to");
  exit;
}

$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* -------------------- Seed demo data once -------------------- */
if (!isset($_SESSION['tickets'])) {
  $_SESSION['tickets'] = [
    ['id'=>'1','title'=>'Fix login bug','description'=>'Users are unable to login with their credentials','status'=>'open','priority'=>'high'],
    ['id'=>'2','title'=>'Update dashboard UI','description'=>'Refresh the dashboard with new design system','status'=>'in_progress','priority'=>'medium'],
    ['id'=>'3','title'=>'Add export feature','description'=>'Allow users to export reports as PDF','status'=>'closed','priority'=>'low']
  ];
}

/* -------------------- Routes -------------------- */

/* Landing */
if ($path === '/' || $path === '/home') {
  render($twig, 'landing.html.twig', ['title' => 'TicketFlow']);
  exit;
}

/* ---------- AUTH: SIGNUP ---------- */
if ($path === '/auth/signup') {
  if ($method === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirmPassword'] ?? '';

    $errors = ['email'=>'','password'=>'','confirmPassword'=>''];

    if ($email === '') $errors['email'] = 'Email is required';
    elseif (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) $errors['email'] = 'Please enter a valid email';

    if ($password === '') $errors['password'] = 'Password is required';
    elseif (strlen($password) < 6) $errors['password'] = 'Password must be at least 6 characters';

    if ($confirm === '') $errors['confirmPassword'] = 'Please confirm your password';
    elseif ($confirm !== $password) $errors['confirmPassword'] = 'Passwords do not match';

    $hasErrors = implode('', $errors) !== '';

    if ($hasErrors) {
      render($twig, 'auth/signup.html.twig', [
        'title'  => 'Sign up',
        'form'   => ['email'=>$email],
        'errors' => $errors
      ]);
    } else {
      // In a real app, save user. Here we just celebrate and send to login.
      $_SESSION['flash'] = ['type'=>'success','text'=>'Account created successfully! Please sign in.'];
      redirect('/auth/login');
    }
    exit;
  }

  render($twig, 'auth/signup.html.twig', ['title'=>'Sign up','errors'=>[]]);
  exit;
}

/* ---------- AUTH: LOGIN ---------- */
if ($path === '/auth/login') {
  if ($method === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $errors = ['email'=>'','password'=>''];

    if ($email === '') $errors['email'] = 'Email is required';
    elseif (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) $errors['email'] = 'Please enter a valid email';

    if ($password === '') $errors['password'] = 'Password is required';
    elseif (strlen($password) < 6) $errors['password'] = 'Password must be at least 6 characters';

    $hasErrors = implode('', $errors) !== '';

    if ($hasErrors) {
      render($twig, 'auth/login.html.twig', [
        'title'  => 'Login',
        'form'   => ['email'=>$email],
        'errors' => $errors
      ]);
    } else {
      // Pretend auth is real
      $_SESSION['user'] = $email;
      $_SESSION['flash'] = ['type'=>'success','text'=>'Login successful!'];
      redirect('/dashboard');
    }
    exit;
  }

  render($twig, 'auth/login.html.twig', ['title'=>'Login','errors'=>[]]);
  exit;
}

/* ---------- AUTH: LOGOUT ---------- */
if ($path === '/auth/logout' && $method === 'POST') {
  unset($_SESSION['user']);
  $_SESSION['flash'] = ['type'=>'success','text'=>'Logged out.'];
  redirect('/auth/login');
}

/* ---------- Require Login Helper ---------- */
$requireLogin = function (): void {
  if (empty($_SESSION['user'])) redirect('/auth/login');
};

/* ---------- DASHBOARD ---------- */
if ($path === '/dashboard') {
  $requireLogin();

  $tickets = $_SESSION['tickets'];
  render($twig, 'dashboard.html.twig', [
    'title'   => 'Dashboard',
    'tickets' => $tickets
  ]);
  exit;
}

/* ===================== TICKETS CRUD ===================== */

/* List + Create */
if ($path === '/tickets') {
  $requireLogin();

  if ($method === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $priority    = $_POST['priority'] ?? 'medium';
    $status      = $_POST['status'] ?? 'open';
    $description = trim($_POST['description'] ?? '');

    $errors = ['title'=>''];
    if ($title === '') $errors['title'] = 'Title is required';

    if ($errors['title'] !== '') {
      render($twig, 'tickets/index.html.twig', [
        'title'   => 'Tickets',
        'tickets' => $_SESSION['tickets'],
        'form'    => compact('title','priority','status','description'),
        'errors'  => $errors
      ]);
      exit;
    }

    $new = [
      'id'          => (string) (time() . rand(100, 999)),
      'title'       => $title,
      'description' => $description,
      'status'      => in_array($status, ['open','in_progress','closed'], true) ? $status : 'open',
      'priority'    => in_array($priority, ['low','medium','high'], true) ? $priority : 'medium',
    ];
    array_unshift($_SESSION['tickets'], $new);
    $_SESSION['flash'] = ['type'=>'success','text'=>'Ticket created successfully!'];
    redirect('/tickets');
  }

  render($twig, 'tickets/index.html.twig', [
    'title'   => 'Tickets',
    'tickets' => $_SESSION['tickets'],
    'errors'  => []
  ]);
  exit;
}

/* Update */
if ($method === 'POST' && preg_match('#^/tickets/(\d+)/update$#', $path, $m)) {
  $requireLogin();
  $id          = $m[1];
  $title       = trim($_POST['title'] ?? '');
  $status      = $_POST['status'] ?? 'open';
  $priority    = $_POST['priority'] ?? 'medium';
  $description = trim($_POST['description'] ?? '');

  if ($title === '') {
    $_SESSION['flash'] = ['type'=>'error','text'=>'Title is required'];
    redirect('/tickets');
  }

  foreach ($_SESSION['tickets'] as &$t) {
    if ($t['id'] === $id) {
      $t['title']       = $title;
      $t['status']      = in_array($status, ['open','in_progress','closed'], true) ? $status : $t['status'];
      $t['priority']    = in_array($priority, ['low','medium','high'], true) ? $priority : $t['priority'];
      $t['description'] = $description;
      break;
    }
  }
  unset($t);

  $_SESSION['flash'] = ['type'=>'success','text'=>'Ticket updated successfully!'];
  redirect('/tickets');
}

/* Delete */
if ($method === 'POST' && preg_match('#^/tickets/(\d+)/delete$#', $path, $m)) {
  $requireLogin();
  $id = $m[1];

  $_SESSION['tickets'] = array_values(array_filter(
    $_SESSION['tickets'],
    fn($t) => $t['id'] !== $id
  ));
  $_SESSION['flash'] = ['type'=>'success','text'=>'Ticket deleted successfully!'];
  redirect('/tickets');
}

/* ---------- 404 ---------- */
http_response_code(404);
render($twig, '404.html.twig', ['title' => 'Not Found']);
