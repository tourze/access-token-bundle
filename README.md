# Access Token Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/access-token-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/access-token-bundle)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg)](https://packagist.org/packages/tourze/access-token-bundle)
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)
[![Build Status](https://img.shields.io/github/actions/workflow/status/owner/repo/ci.yml)](https://github.com/owner/repo/actions)
[![Code Coverage](https://img.shields.io/codecov/c/github/owner/repo)](https://codecov.io/github/owner/repo)

A Symfony bundle for managing access tokens with built-in security features,
token lifecycle management, and API authentication support.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)  
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Setup](#setup)
- [API Usage](#api-usage)
- [Configuration Options](#configuration-options)
- [Advanced Examples](#advanced-examples)
- [API Reference](#api-reference)
- [Contributing](#contributing)
- [Testing](#testing)
- [License](#license)
- [References](#references)

## Features

- 🔐 **Secure Token Management** - Generate and manage secure access tokens for API authentication
- 🚫 **Multiple Login Prevention** - Optional feature to prevent users from having multiple active sessions
- 🔄 **Token Lifecycle Management** - Automatic token expiration and renewal
- 🧹 **Automatic Cleanup** - Built-in command to clean up expired tokens
- 🌐 **API Ready** - Pre-built API endpoints for token management
- 🛡️ **Symfony Security Integration** - Seamless integration with Symfony's security component
- 📱 **Device Tracking** - Track device information for each token
- ⚡ **High Performance** - Optimized database queries with Doctrine ORM

## Requirements

- PHP 8.1 or higher
- Symfony 6.4 or higher
- Doctrine ORM 3.0 or higher

## Installation

```bash
composer require tourze/access-token-bundle
```

## Quick Start

Get started with Access Token Bundle in just a few minutes:

### 1. Install and Setup

```bash
# Install the bundle
composer require tourze/access-token-bundle

# Run database migration to create access token table
php bin/console doctrine:migrations:migrate
```

### 2. Configure Security (optional)

Add access token authentication to your `config/packages/security.yaml`:

```yaml
security:
    firewalls:
        api:
            pattern: ^/api
            stateless: true
            access_token:
                token_handler: Tourze\AccessTokenBundle\Service\AccessTokenHandler
```

### 3. Create Your First Token

```bash
# Create an access token for a user via command line
php bin/console app:create-access-token your_username --expires=3600

# Or use the service in your code
use Tourze\Tourze\AccessTokenBundle\Service\AccessTokenService;

$token = $accessTokenService->createToken($user, 3600, 'My Device');
echo $token->getToken(); // Use this token for API calls
```

### 4. Test Your Token

```bash
# Test the token with curl
curl -H "Authorization: Bearer your_token_here" http://localhost/api/test
```

That's it! You now have working access token authentication for your Symfony application.

## Setup

### Enable the Bundle

If you're using Symfony Flex, the bundle is automatically enabled. Otherwise, add it to your `config/bundles.php`:

```php
return [
    // ...
    Tourze\AccessTokenBundle\Tourze\AccessTokenBundle::class => ['all' => true],
];
```

### Configure Security

Add the access token authentication to your security configuration:

```yaml
# config/packages/security.yaml
security:
    firewalls:
        api:
            pattern: ^/api
            stateless: true
            access_token:
                token_handler: Tourze\AccessTokenBundle\Service\AccessTokenHandler
```

### Run Database Migration

Create the access token table:

```bash
php bin/console doctrine:migrations:migrate
```

## API Usage

### Basic Usage

1. **Create access token**
```php
use Tourze\Tourze\AccessTokenBundle\Service\AccessTokenService;

// Inject service
$accessTokenService = $container->get(AccessTokenService::class);

// Create token for user (expires in 1 hour, device: iOS App)
$token = $accessTokenService->createToken($user, 3600, 'iOS App');
echo $token->getToken(); // Output token value
```

2. **Validate access token**
```php
// Validate and extend token
$validToken = $accessTokenService->validateAndExtendToken($tokenValue, 3600);
if ($validToken) {
    // Token is valid
    $user = $validToken->getUser();
}
```

3. **Revoke token**
```php
$accessTokenService->revokeToken($token);
```

### Command Line Tools

1. Create access token
```bash
php bin/console app:create-access-token username --expires=3600 --device="Mobile App"
```

2. Cleanup expired tokens
```bash
php bin/console app:cleanup-access-tokens
php bin/console app:cleanup-access-tokens --dry-run  # View only, don't delete
```

### API Endpoints

The bundle provides the following API endpoints:

- `GET /api/user` - Get current user information
- `GET /api/tokens` - Get all tokens for current user
- `POST /api/token/revoke/{id}` - Revoke specified token
- `GET /api/test` - Test API access

## Configuration Options

### Environment Variables

Configure the following environment variables in your `.env` file:

```env
# Access token renewal time (seconds), default 3600 seconds (1 hour)
ACCESS_TOKEN_RENEWAL_TIME=3600

# Prevent multiple login, default true (prevent multiple login)
# true: Creating new token will automatically revoke all existing tokens for the user
# false: Allow user to have multiple valid tokens simultaneously
ACCESS_TOKEN_PREVENT_MULTIPLE_LOGIN=true
```

### Security Configuration

Configure access token authentication in `config/packages/security.yaml`:

```yaml
security:
    firewalls:
        api:
            pattern: ^/api
            stateless: true
            access_token:
                token_handler: Tourze\AccessTokenBundle\Service\AccessTokenHandler
```

### Database Configuration

Make sure to run database migrations to create the access token table:

```bash
php bin/console doctrine:migrations:migrate
```

## Advanced Examples

### Complete Login Flow Example

```php
use Tourze\Tourze\AccessTokenBundle\Service\AccessTokenService;
use Symfony\Component\HttpFoundation\JsonResponse;

class AuthController extends AbstractController
{
    public function login(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        AccessTokenService $accessTokenService
    ): JsonResponse {
        $username = $request->request->get('username');
        $password = $request->request->get('password');
        
        // Validate user credentials
        $user = $this->userRepository->findOneBy(['username' => $username]);
        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Invalid username or password'], 401);
        }
        
        // Create access token
        $deviceInfo = $request->headers->get('User-Agent');
        $token = $accessTokenService->createToken($user, 86400, $deviceInfo);
        
        return $this->json([
            'access_token' => $token->getToken(),
            'expires_at' => $token->getExpiresAt()->format('Y-m-d H:i:s'),
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
            ]
        ]);
    }
}
```

### Using Access Token to Call API

```bash
# Use Bearer Token authentication
curl -H "Authorization: Bearer your_access_token_here" \
     http://localhost/api/user
```

### Prevent Multiple Login Example

When `ACCESS_TOKEN_PREVENT_MULTIPLE_LOGIN=true`:

```php
// User first login
$token1 = $accessTokenService->createToken($user, 3600, 'Web Browser');

// User login on another device, previous token will be automatically revoked
$token2 = $accessTokenService->createToken($user, 3600, 'Mobile App');

// Now $token1 is invalid, only $token2 is valid
```

When `ACCESS_TOKEN_PREVENT_MULTIPLE_LOGIN=false`:

```php
// User can have multiple valid tokens simultaneously
$token1 = $accessTokenService->createToken($user, 3600, 'Web Browser');
$token2 = $accessTokenService->createToken($user, 3600, 'Mobile App');

// Both $token1 and $token2 remain valid
```

## API Reference

### Token Repository Methods

```php
use Tourze\Tourze\AccessTokenBundle\Repository\AccessTokenRepository;

// Find tokens by user
$tokens = $accessTokenRepository->findByUser($user);

// Find active tokens
$activeTokens = $accessTokenRepository->findActiveTokensByUser($user);

// Count expired tokens
$expiredCount = $accessTokenRepository->countExpired();

// Delete expired tokens
$deletedCount = $accessTokenRepository->deleteExpired();
```

### Custom Token Validation

```php
use Tourze\Tourze\AccessTokenBundle\Entity\AccessToken;

// Custom validation logic
$token = $accessTokenService->findToken($tokenValue);
if ($token && !$token->isExpired()) {
    // Additional custom checks
    if ($token->getDeviceInfo() === $expectedDevice) {
        // Token is valid for this device
    }
}
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Testing

Run the test suite:

```bash
./vendor/bin/phpunit packages/access-token-bundle/tests
```

Run PHPStan analysis:

```bash
php -d memory_limit=2G ./vendor/bin/phpstan analyse packages/access-token-bundle
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## References

- [Symfony Security Component](https://symfony.com/doc/current/security.html)
- [Doctrine ORM](https://www.doctrine-project.org/projects/orm.html)
- [Symfony Access Token Authenticator](https://symfony.com/doc/current/security/access_token.html)
