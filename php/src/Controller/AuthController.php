<?php

namespace App\Controller;

use App\User\Domain\UserRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly CsrfTokenManagerInterface $csrf,
    ) {
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, SessionInterface $session): Response
    {
        $errors = [];
        $success = false;

        if ($request->isMethod('POST')) {
            $tokenValue = (string)$request->request->get('_csrf_token');
            if (!$this->csrf->isTokenValid(new CsrfToken('register', $tokenValue))) {
                $errors[] = 'Invalid CSRF token.';
            }

            $email = trim((string)$request->request->get('email'));
            $password = (string)$request->request->get('password');
            $passwordConfirm = (string)$request->request->get('password_confirm');

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            }
            if ($password === '' || strlen($password) < 6) {
                $errors[] = 'Password must be at least 6 characters.';
            }
            if ($password !== $passwordConfirm) {
                $errors[] = 'Passwords do not match.';
            }

            if (!$errors) {
                $existing = $this->users->findByEmail($email);
                if ($existing) {
                    $errors[] = 'An account with this email already exists.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    try {
                        $this->users->create($email, $hash);
                        $success = true;
                        // Do NOT auto-login; defer to Symfony Security login flow
                        // Redirect to the login page so the user authenticates via the firewall
                        return new RedirectResponse($this->generateUrl('app_login'));
                    } catch (\Throwable $e) {
                        $errors[] = 'Could not create account. Please try again.';
                    }
                }
            }
        }

        return $this->render('auth/register.html.twig', [
            'errors' => $errors,
            'success' => $success,
            'last_email' => $request->request->get('email', ''),
            'csrf_token' => $this->csrf->getToken('register')->getValue(),
        ]);
    }

    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authUtils): Response
    {
        $error = $authUtils->getLastAuthenticationError();
        $lastEmail = $authUtils->getLastUsername();

        return $this->render('auth/login.html.twig', [
            'error' => $error,
            'last_email' => $lastEmail,
            'csrf_token' => $this->csrf->getToken('authenticate')->getValue(),
        ]);
    }

    // POST /login is handled by the Security firewall (form_login). No controller needed.

    #[Route('/login/success', name: 'app_login_success', methods: ['GET'])]
    public function loginSuccess(SessionInterface $session): Response
    {
        return $this->render('auth/success.html.twig', [
            'user_email' => $session->get('user_email'),
            'session_id' => $session->getId(),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Controller can be blank: it will be intercepted by the logout key on your firewall.
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
