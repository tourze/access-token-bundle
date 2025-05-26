# access-token-bundle

Token Management

## Installation

```bash
composer require tourze/access-token-bundle
```

## Usage

### Basic Usage

1. Create access token
```php
use AccessTokenBundle\Service\AccessTokenService;

// Inject service
$accessTokenService = $container->get(AccessTokenService::class);

// Create token for user
$token = $accessTokenService->createToken($user, 3600, 'iOS App');
echo $token->getToken(); // Output token value
```

2. Validate access token
```php
// Validate and extend token
$validToken = $accessTokenService->validateAndExtendToken($tokenValue, 3600);
if ($validToken) {
    // Token is valid
    $user = $validToken->getUser();
}
```

3. Revoke token
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

## Configuration

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
                token_handler: AccessTokenBundle\Service\AccessTokenHandler
```

### Database Configuration

Make sure to run database migrations to create the access token table:

```bash
php bin/console doctrine:migrations:migrate
```

## Examples

### Complete Login Flow Example

```php
use AccessTokenBundle\Service\AccessTokenService;
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

## References

- [Symfony Security Component](https://symfony.com/doc/current/security.html)
- [Doctrine ORM](https://www.doctrine-project.org/projects/orm.html)
