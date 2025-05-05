<?php

namespace Api\Services;

use Api\Gateways\UserGateway;

class AuthFormValidation {
    public function __construct(
        private UserGateway $user_gateway
    ) {
        
    }

    public function getUsernameValidationError(?string $username, bool $searchDb = true): ?string {
        if (empty($username)) {
            return 'Username is required.';
        } 
        else if (strlen($username) > 20) {
            return 'Username cannot exceed 20 characters.';
        }
        else if ($searchDb === true && $this->user_gateway->getByUsername($username)) {
            return 'Username is already taken, please try another one.';
        }

        return null;
    }

    public function getEmailValidationError(?string $email, bool $searchDb = true): ?string {
        if (empty($email)) {
            return 'Email address is required.';
        }
        else if (strlen($email) > 255) {
            return 'Email address cannot exceed 255 characters.';
        }
        else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Enter a valid email address.';
        }
        else if ($searchDb === true && $this->user_gateway->getByEmail($email)) {
            return 'Email address is already taken, please try another one.';
        }

        return null;
    }

    public function getPasswordValidationError(?string $password): ?string {
        if (empty($password)) {
            return 'Password is required.';
        }
        else if (strlen($password) < 8) {
            return 'Password must be at least 8 characters long.';
        } 
        else if (strlen($password) > 72) {
            return 'Password cannot exceed 72 characters.';
        }
        else if (preg_match('/\s/', $password)) {
            return 'Password must not contain spaces.';
        }
        else if (!preg_match('/[a-z]/', $password)) {
            return 'Password must contain at least one lowercase letter.';
        }
        else if (!preg_match('/[A-Z]/', $password)) {
            return 'Password must contain at least one uppercase letter.';
        }
        else if (!preg_match('/\d/', $password)) {
            return 'Password must contain at least one digit.';
        }

        return null;
    }

    public function getRepeatedPasswordValidationError(?string $password, ?string $repeatedPassword): ?string {
        if (empty($repeatedPassword)) {
            return 'You must confirm your password.';
        }
        else if ($password !== $repeatedPassword) {
            return 'The passwords entered do not match.';
        }

        return null;
    }
}