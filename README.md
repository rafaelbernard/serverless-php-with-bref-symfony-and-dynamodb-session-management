# Building a Serverless PHP Application with Bref, Symfony, and DynamoDB Session Management

## Introduction

Serverless apps are fantastic for automatic scaling, but there’s a catch: they expect you to be stateless. Most web applications, however, rely on sessions to remember users and persist state. Traditional PHP session handlers store data on the filesystem, which doesn’t play nicely with ephemeral AWS Lambda instances—your sessions vanish as soon as the instance disappears.

The usual fix? Fire up a Redis cluster. Works, but suddenly you’ve added infrastructure, ongoing maintenance, and extra costs. Your “serverless” app feels a lot less serverless.

What if we could manage sessions **without touching Redis or any other server**?

In this post, we’ll show you how to build a **truly serverless PHP app** using **Bref**, **Symfony**, and **DynamoDB** for session management. Along the way, you’ll see:

- A **custom DynamoDB-backed session handler** that replaces filesystem sessions
- How to deploy your app via **Lambda Function URLs** using AWS CDK
- Storing **CSRF tokens in DynamoDB** for fully stateless operation
- **Single-table design patterns** for efficient multi-entity storage

By the end, you’ll know not just *how* to implement this architecture, but also *when* it makes sense and what trade-offs you’re accepting.

## The Challenge: Sessions in Serverless

Before we dive into the solution, let’s understand why traditional PHP sessions fail in serverless environments.

1. **Ephemeral Storage**: Lambda instances can vanish at any time. Writing sessions to `/tmp` is like storing them in sand. They disappear when the instance is recycled.
2. **No Shared Filesystem**: Each Lambda invocation runs on its own instance. User A’s session written by instance 1 is invisible to instance 2. That’s a problem if your user expects to stay logged in.
3. **Horizontal Scaling Woes**: Lambda scales horizontally automatically. Without centralized session storage, each instance is isolated. Consistent session management? Forget it.

### The Traditional Solution: Redis/ElastiCache

Most serverless PHP guides suggest Redis. While it works, it comes with headaches:

- **Infrastructure complexity**: VPCs, subnets, and security groups
- **Maintenance burden**: Patching, monitoring, capacity planning
- **Cold start penalty**: VPC-connected Lambdas can take 1–2 extra seconds

💡 **Better idea**: DynamoDB. It’s fully managed, serverless, and scales automatically. No Redis cluster, no maintenance, just pay for what you use.

## How We Manage Books and Authors (Serverless Style)

Imagine you’re building a multi-tenant SaaS app, like an internal tool for managing books and authors. Each user needs a session, and each organization manages its own data. DynamoDB’s single-table design can elegantly handle all this. Serverless scaling takes care of traffic spikes automatically.

Here’s what this example demonstrates:

- **Multi-entity relationships**: Books belong to authors
- **CRUD operations**: Create, read, update, and delete across related entities
- **Session-dependent workflows**: Adding/editing books requires authentication
- **Real-world complexity**: More than a simple counter, less than a full e-commerce platform

### Connecting to Real Use Cases

This architecture shines in scenarios like:

- **Unpredictable traffic**: Seasonal spikes when authors release new books
- **Session management**: Authors need persistent sessions to edit content
- **Cost efficiency**: During quiet periods, you pay pennies; during spikes, DynamoDB scales automatically
- **Zero maintenance**: No Redis clusters to monitor, no database servers to patch

The book management example proves that this approach isn’t just theoretical. It’s production-ready.

## Architecture Overview

To build a serverless PHP application that supports sessions, CSRF protection, and persistent data, we follow a **stateful/stateless separation** pattern. This makes the architecture scalable, cost-efficient, and easy to maintain.

### 1. Stateful Layer: Persistent Data

This layer is responsible for storing all data that needs to survive beyond a single Lambda invocation.

- **DynamoDB Table**
    - Uses a **single-table design** to store sessions, CSRF tokens, users, books, and authors.
    - **TTL enabled** for automatic session expiration.
    - **On-demand billing** ensures automatic scaling with traffic.
    - Built-in **multi-AZ replication** provides high availability.

- **Benefits**
    - No infrastructure to manage or patch.
    - Automatically scales with unpredictable traffic.
    - Centralized storage simplifies queries and operations.

### 2. Stateless Layer: Application Logic

This layer runs the application code and handles requests without storing any persistent state locally.

- **Lambda Function**
    - Runs **PHP-FPM** via Bref.
    - Handles HTTP requests directly using a **Lambda Function URL** (HTTPS endpoint).
    - No VPC required to access DynamoDB, reducing cold start latency.

- **Static Assets**
    - Stored in **S3** (optionally served via CloudFront) to keep Lambda stateless.

- **Benefits**
    - Scales automatically with traffic.
    - Cost-efficient: pay only for actual requests.
    - Stateless logic simplifies deployment and updates.

This design ensures a **truly serverless PHP application** that handles session state, persistent data, and scalable workloads without the operational overhead of managing Redis or other caching layers.

## DynamoDB Session Handler and CSRF Implementation

### Session Handler Implementation

In a serverless PHP application, traditional session storage (files or local memory) doesn’t work because Lambda functions are **ephemeral**. Each invocation may run on a different container, so we need a centralized, persistent session store.

The core of our solution is a custom session handler that implements PHP's `SessionHandlerInterface`.

#### How It Works

- **Sessions are stored in DynamoDB** instead of the filesystem.
- Each session has a unique `session_id`, which becomes the partition key (`PK`) in DynamoDB.
- Sessions include the serialized PHP session data and an **expiration timestamp** (TTL).
- The handler automatically reads/writes session data on `session_start()` and `session_write_close()`.

#### Key Features

1. **Automatic Expiration**
    - DynamoDB TTL ensures sessions are removed automatically after expiration.
2. **Atomic Operations**
    - `PutItem` and `UpdateItem` guarantee consistent writes, even with concurrent requests.
3. **Scalable**
    - Can handle thousands of concurrent sessions without extra infrastructure.
4. **Serverless-friendly**
    - No local storage, no Redis, fully compatible with Lambda statelessness.

#### Implementation

```php
<?php

namespace App\Session;

use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Input\DeleteItemInput;
use AsyncAws\DynamoDb\Input\GetItemInput;
use AsyncAws\DynamoDb\Input\PutItemInput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;

/**
 * A minimal DynamoDB-backed PHP session handler using AsyncAws.
 *
 * Table design (single-table compatible):
 *  - PK: "SESSION"
 *  - SK: "SID#<session_id>"
 *  - data: base64-encoded session payload (string)
 *  - expiresAt: unix epoch seconds (number), enable DynamoDB TTL on this attribute
 *
 * Garbage collection is handled by DynamoDB's TTL, so gc() is a no-op.
 */
class DynamoDbSessionHandler implements \SessionHandlerInterface
{
    private const string PK_VALUE = 'SESSION';
    private const string SK_PREFIX = 'SID#';

    public function __construct(
        private readonly DynamoDbClient $dynamoDb,
        private readonly string $tableName,
        private readonly int $ttlSeconds = 3600,
    ) {}

    public function open(string $path, string $name): bool
    {
        // Nothing to do
        return true;
    }

    public function close(): bool
    {
        // Nothing to do
        return true;
    }

    public function read(string $id): string
    {
        $result = $this->dynamoDb->getItem(new GetItemInput([
            'TableName' => $this->tableName,
            'Key' => [
                'PK' => new AttributeValue(['S' => self::PK_VALUE]),
                'SK' => new AttributeValue(['S' => self::SK_PREFIX . $id]),
            ],
            // Strongly consistent read to reduce stale sessions
            'ConsistentRead' => true,
        ]));

        $item = $result->getItem();
        if (!$item || !isset($item['data'])) {
            return '';
        }

        $encoded = $item['data']->getS();
        if ($encoded === null) {
            return '';
        }

        $payload = base64_decode($encoded, true);
        return $payload === false ? '' : $payload;
    }

    public function write(string $id, string $data): bool
    {
        $expiresAt = time() + $this->ttlSeconds;

        $this->dynamoDb->putItem(new PutItemInput([
            'TableName' => $this->tableName,
            'Item' => [
                'PK' => new AttributeValue(['S' => self::PK_VALUE]),
                'SK' => new AttributeValue(['S' => self::SK_PREFIX . $id]),
                'data' => new AttributeValue(['S' => base64_encode($data)]),
                'expiresAt' => new AttributeValue(['N' => (string) $expiresAt]),
            ],
        ]));

        return true;
    }

    public function destroy(string $id): bool
    {
        $this->dynamoDb->deleteItem(new DeleteItemInput([
            'TableName' => $this->tableName,
            'Key' => [
                'PK' => new AttributeValue(['S' => self::PK_VALUE]),
                'SK' => new AttributeValue(['S' => self::SK_PREFIX . $id]),
            ],
        ]));

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        // Rely on DynamoDB TTL to expire items; nothing to scan/delete here.
        return 0;
    }
}
```

#### Why it matters?
This approach:

- Keeps your PHP sessions serverless-compatible.
- Avoids cold-start pitfalls associated with local or in-memory session storage.
- Provides a reliable, scalable, and fully managed solution for stateful data in a stateless environment.

### CSRF Token Storage

In a serverless environment, CSRF tokens must be handled carefully. Because Lambda executions are stateless, tokens cannot be stored in memory or on the filesystem. Instead, CSRF tokens are persisted in DynamoDB alongside session data.

This approach ensures tokens remain valid and verifiable across multiple Lambda invocations.

#### How CSRF Tokens Are Stored

Each CSRF token is stored as a dedicated item in the DynamoDB table:

- Tokens are associated with a specific action
- Each token has a unique identifier
- An expiration timestamp is stored for automatic cleanup

This makes CSRF token storage consistent, durable, and serverless-compatible.

#### Data Model

CSRF tokens follow the same single-table design pattern used elsewhere in the application.

| Attribute | Value |
|----------|-------|
| PK       | `CSRF` |
| SK       | `TOKEN#<token_id>` |
| session  | `<session_id>` |
| expiresAt| `<timestamp>` |

Using a distinct partition key avoids contention and allows tokens to scale independently from session traffic.

#### Lifecycle

1. A CSRF token is generated when a form is rendered.
2. The token is persisted in DynamoDB.
3. On form submission, the token is retrieved and validated.
4. After validation or expiration, the token is deleted or allowed to expire via TTL.

This lifecycle mirrors traditional CSRF handling while remaining compatible with Lambda’s

#### Implementation

```php
class DynamoDbCsrfTokenStorage implements CsrfTokenStorageInterface
{
    private const string PK_VALUE = 'CSRF';
    private const string SK_PREFIX = 'TOKEN#';

    public function getToken(string $tokenId): string
    {
        $result = $this->dynamoDb->getItem(new GetItemInput([
            'TableName' => $this->tableName,
            'Key' => [
                'PK' => new AttributeValue(['S' => self::PK_VALUE]),
                'SK' => new AttributeValue(['S' => self::SK_PREFIX . $tokenId]),
            ],
        ]));

        $item = $result->getItem();
        return $item['value']->getS() ?? '';
    }

    public function setToken(string $tokenId, string $token): void
    {
        $expiresAt = time() + $this->ttlSeconds;

        $this->dynamoDb->putItem(new PutItemInput([
            'TableName' => $this->tableName,
            'Item' => [
                'PK' => new AttributeValue(['S' => self::PK_VALUE]),
                'SK' => new AttributeValue(['S' => self::SK_PREFIX . $tokenId]),
                'value' => new AttributeValue(['S' => $token]),
                'expiresAt' => new AttributeValue(['N' => (string) $expiresAt]),
            ],
        ]));
    }
}
```

This ensures CSRF protection works seamlessly across multiple Lambda invocations.

## Symfony Configuration

Configuring Symfony correctly is key for serverless PHP apps to work reliably with Lambda, DynamoDB, and Bref. Here’s how we set it up.

### 1. Session Storage

We replace the default PHP session handler with our **DynamoDBSessionHandler**:

```yaml
# config/packages/framework.yaml
framework:
    session:
        handler_id: App\Session\DynamoDBSessionHandler
        cookie_secure: auto
        cookie_samesite: lax
        cookie_lifetime: 3600  # 1 hour
```
Notes:

- `handler_id` points to our custom service.
- `cookie_secure: auto` ensures HTTPS enforcement on Lambda URLs or custom domains.
- `cookie_lifetime` aligns with DynamoDB TTL for consistency.

### 2. Service definition

Register the DynamoDB session handler as a Symfony service:

```yaml
# config/services.yaml
services:
  App\Session\DynamoDbSessionHandler:
    arguments:
      $tableName: '%book_table_name%'
      $ttlSeconds: '%env(default:session_ttl_seconds:int:SESSION_TTL)%'
```
- `$tableName` comes from environment variables to support multiple environments.
- `$ttl` matches the session lifetime for automatic garbage collection.
  This configuration tells Symfony to use our custom handler for all session operations. The handler is automatically injected with the DynamoDB client through Symfony's autowiring.

### 3. RequestContextListener

To handle dynamic Lambda Function URLs, we register a listener:

```yaml
# config/services.yaml
services:
    App\EventListener\RequestContextListener:
        tags:
            - { name: kernel.event_listener, event: kernel.request, method: onKernelRequest }
```
Purpose:
- Ensures Symfony’s URL generator produces correct URLs.
- Sets proper scheme and host for redirects, forms, and CSRF validation.
- Essential for Lambda Function URLs where host/scheme changes per invocation.

#### Why It’s Needed

Lambda Function URLs:

- Provide a direct HTTPS endpoint (e.g., `https://xyz.lambda-url.us-east-1.on.aws/`)
- Are **dynamic** and unknown at build time
- Require Symfony to know the **scheme and host** at runtime to generate correct URLs

Without a listener:

- Redirects may point to HTTP instead of HTTPS
- CSRF tokens may fail
- Session cookies might be rejected
- OAuth or SSO integrations could break

#### Implementation

```php
<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RequestContext;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 1024)]
class RequestContextListener
{
    public function __construct(private RequestContext $requestContext) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        // Force HTTPS for Lambda URLs and set proper context
        if ($request->headers->get('host') && str_contains($request->headers->get('host'), 'lambda-url')) {
            $this->requestContext->setScheme('https');
            $this->requestContext->setHost($request->headers->get('host'));
            $this->requestContext->setHttpPort(80);
            $this->requestContext->setHttpsPort(443);
            
            $request->server->set('HTTPS', 'on');
            $request->server->set('SERVER_PORT', 443);
            $request->server->set('REQUEST_SCHEME', 'https');
        }
    }
}
```

**The CDK Output Dilemma:**
```typescript
// CDK can output the Lambda URL after deployment
new CfnOutput(this, 'LambdaURL', { 
    value: statelessStack.monolithLambdaFunctionUrl.url 
});
// But this value is only known AFTER deployment completes
// You can't use it as an environment variable in the SAME deployment
```

💡 Tip: This listener is only necessary if you use the Lambda Function URL as the production endpoint. If you use a custom domain, this can be simplified or skipped.

With this configuration, Symfony becomes serverless-ready, maintaining sessions, CSRF protection, and routing behavior seamlessly while leveraging DynamoDB and Lambda.

## Single-Table Design Pattern

All entities (sessions, CSRF tokens, users, books, authors) live in a **single DynamoDB table**. This simplifies the architecture and enables atomic operations across different entities.

| Entity      | PK                  | SK                                      |
|------------|-------------------|----------------------------------------|
| Session    | `SESSION`          | `SID#<session_id>`                      |
| CSRF Token | `CSRF`             | `TOKEN#<token_id>`                      |
| Book       | `BOOK-METADATA`    | `AUTHOR#<author_id>#BOOK#<book_id>`    |
| Author     | `AUTHOR-METADATA`  | `AUTHOR#<author_id>`                    |
| User       | `USER`             | `EMAIL#<email>`                         |

- **Why single-table?**
    - Reduces infrastructure complexity.
    - Simplifies monitoring and backup.
    - Supports atomic transactions across multiple entity types.
    - Aligns with AWS best practices for DynamoDB.

## AWS CDK Infrastructure with Bref

Deploying a serverless Symfony app requires some AWS setup. Using **AWS CDK** with **Bref** makes this smooth, maintainable, and repeatable.

### Why CDK?

- **Infrastructure as code**: Everything is versioned and reproducible.
- **Integration with Symfony**: Easy to link environment variables, DynamoDB, and Lambda functions.
- **Bref-friendly**: Deploy PHP Lambda layers without manually configuring Lambda functions.

### Stateful Stack: DynamoDB Table

```typescript
import { NestedStack } from "aws-cdk-lib";
import * as ddb from "aws-cdk-lib/aws-dynamodb";

export class BlogAppStatefulStack extends NestedStack {
  public readonly ddb: ddb.Table;

  constructor(scope: Construct, id: string, props: MyNestedStackProps) {
    super(scope, id, props);

    this.ddb = new ddb.Table(this, 'ddb', {
      tableName: `${id}-table`,
      partitionKey: { name: 'PK', type: ddb.AttributeType.STRING },
      sortKey: { name: 'SK', type: ddb.AttributeType.STRING },
      billingMode: ddb.BillingMode.PAY_PER_REQUEST,
      deletionProtection: props.shared.environment === 'prod',
      timeToLiveAttribute: 'expiresAt',
    });
  }
}
```

Key features:
- **Generic Key Schema**: `PK` and `SK` enable single-table design
- **TTL Enabled**: `expiresAt` attribute automatically removes expired items
- **Production Protection**: Deletion protection enabled for production environments

### Stateless Stack: Lambda Function with Bref

```typescript
import { packagePhpCode, PhpFpmFunction } from "@bref.sh/constructs";
import * as lambda from "aws-cdk-lib/aws-lambda";
import { FunctionUrl } from "aws-cdk-lib/aws-lambda";

export class BlogAppStatelessStack extends NestedStack {
  public readonly monolithLambda: PhpFpmFunction;
  public monolithLambdaFunctionUrl: FunctionUrl;

  private createLambda(props: MyNestedStackProps, staticAssetsBucket: Bucket, ddb: ddb.Table) {
    const lambdaEnvironment = {
      APP_ENV: props.shared.environment,
      APP_SECRET: appSecret,
      ASSET_URL: `https://${staticAssetsBucket.bucketDomainName}/`,
      AWS_LAMBDA_LOG_FORMAT: 'text',
      BOOK_TABLE_NAME: `${props.shared.stackPrefix}-StatefulStack-table`,
    };

    const monolithLambda = new PhpFpmFunction(this, 'App', {
      handler: 'public/index.php',
      phpVersion: '8.4',
      code: packagePhpCode('php', {
        exclude: ['.env.local', 'bin/'],
      }),
      functionName: `${props.shared.stackPrefix}-App`,
      timeout: Duration.seconds(28),
      memorySize: Size.gibibytes(2).toMebibytes(),
      environment: lambdaEnvironment,
    });

    // Create Function URL with no authentication
    const monolithLambdaFunctionUrl = monolithLambda.addFunctionUrl({ 
      authType: lambda.FunctionUrlAuthType.NONE 
    });

    // Grant DynamoDB permissions
    ddb.grantReadWriteData(monolithLambda);

    return { monolithLambda, monolithLambdaFunctionUrl };
  }
}
```

### Lambda Function URL Configuration

Lambda Function URLs provide a simple HTTPS endpoint without needing API Gateway:

```typescript
const monolithLambdaFunctionUrl = monolithLambda.addFunctionUrl({ 
  authType: lambda.FunctionUrlAuthType.NONE 
});
```

**Benefits of Lambda URLs:**
- **Simplicity**: Direct HTTPS endpoint without API Gateway complexity
- **Cost**: No API Gateway charges
- **Performance**: One less hop in the request path
- **Built-in HTTPS**: Automatic TLS certificate management

**Configuration Options:**
- `authType: NONE`: Public access (suitable for web applications)
- `authType: AWS_IAM`: Requires AWS signature (for service-to-service communication)

### Main Stack: Orchestration

```typescript
export class BlogApp extends Stack {
  constructor(scope: Construct, id: string, props: MyStackProps) {
    super(scope, id, props);

    const stackPrefix = props.shared.envStackPrefix;
    
    const statefulStack = new BlogAppStatefulStack(
      this, `${stackPrefix}-StatefulStack`, props
    );
    
    const statelessStack = new BlogAppStatelessStack(
      this, `${stackPrefix}-StatelessStack`, props, statefulStack
    );

    // Output important values
    new CfnOutput(this, 'Lambda', { 
      value: statelessStack.monolithLambda.functionName 
    });
    new CfnOutput(this, 'LambdaURL', { 
      value: statelessStack.monolithLambdaFunctionUrl.url 
    });
    new CfnOutput(this, 'DynamoDb', { 
      value: statefulStack.ddb.tableName 
    });
  }
}
```

### Deployment with CDK

With the infrastructure defined, deploying the application becomes a repeatable and predictable process. This section focuses on **how the application is built, deployed, and updated** using AWS CDK.

#### Local Development Environment

Local development mirrors the production setup as closely as possible while remaining lightweight.

- Docker is used to provide a consistent PHP environment.
- A Makefile abstracts common commands to reduce cognitive load.
- Symfony runs locally with the same session and configuration logic used in Lambda.

You can run:

```bash
# Pre-requisite - source your aws profile
make up
```

You can check logs via `make logs`. And get into the container with `make bash`. The application will be available at `http://localhost:8000`, but it might fail to load as there is no existent DynamoDB to connect with. You can check local `.env` file for environment variables.

#### Deploying

Deploy the application using standard CDK commands (inside the container):

```bash
# Pre-requisite - Bootstrap CDK if this is your first deployment - npx cdk bootstrap aws://<ACCOUNT_ID>/<REGION>
# Install dependencies
npm run deploy
```

Alternatively, you can use the `Makefile` command outsite the container:
```bash
make deploy
```

#### What Gets Created

The deployment creates:
1. DynamoDB table with TTL enabled
2. Lambda function with PHP 8.4 runtime (via Bref)
3. Lambda Function URL for HTTPS access
4. S3 bucket for static assets
5. IAM roles and permissions

The output should be similar to:

```bash
BlogApp (sandbox-blog-app): deploying... [1/1]
sandbox-blog-app: creating CloudFormation changeset...

 ✅  BlogApp (sandbox-blog-app)

✨  Deployment time: 148.76s

Outputs:
BlogApp.AssetsBucket = sandbox-blog-app-sandboxbloga-assetsbucket5cb76180-5lu45xsqvuym
BlogApp.DynamoDb = sandbox-BlogApp-StatefulStack-table
BlogApp.Lambda = sandbox-BlogApp-App
BlogApp.LambdaURL = https://kiv7utcwku6gihqgs4bfkeuzma0oaylo.lambda-url.us-east-1.on.aws/
Stack ARN:
arn:aws:cloudformation:us-east-1:973974862728:stack/sandbox-blog-app/9ba92580-e50b-11f0-a602-0afffb8dc1a9

✨  Total time: 163.03s
```

In this case, `https://kiv7utcwku6gihqgs4bfkeuzma0oaylo.lambda-url.us-east-1.on.aws/` is the Lambda public URL.

When you access the URL, you will see a log-in form. You can use the "Register" link to create a login. Use it and you will be able to manage Authors and Books. Try to log out and access the pages directly.

![Login](https://rafael.bernard-araujo.com/wp-content/uploads/2025/12/301225-1.png)

![Register](https://rafael.bernard-araujo.com/wp-content/uploads/2025/12/301225-2.png)

![Main](https://rafael.bernard-araujo.com/wp-content/uploads/2025/12/301225-3.png)

Internally it will execute a series of commands:
```bash
# clean
npm run clean && \
# execute php packaging including composer install and npm build for symfony
npm run package:sandbox && \ 
# deploy as a sandbox not requiring approval
NODE_ENV=sandbox cdk deploy --require-approval never
```

There is a prod version executing `make deploy:prod`.

### Testing the Session Implementation

The application includes a test endpoint to verify session persistence:

```php
#[Route('/session-test', name: 'session_test')]
public function test(Request $request): JsonResponse
{
    $session = $request->getSession();
    $counter = $session->get('counter', 0);
    $session->set('counter', $counter + 1);

    return new JsonResponse([
        'message' => 'Session test',
        'session_id' => $session->getId(),
        'counter' => $session->get('counter'),
        'handler' => get_class($session->getMetadataBag()->getMetadata('handler')),
    ]);
}
```

Test with curl:

```bash
# First request creates session
curl -i -c cookie.txt https://your-lambda-url/session-test

# Subsequent requests increment counter
curl -i -b cookie.txt https://your-lambda-url/session-test
curl -i -b cookie.txt https://your-lambda-url/session-test

# outputs
➜ curl -i -c cookie.txt https://your-lambda-url/session-test
{"message":"Session incremented","session_id":"02b0c08e1ccd5f3ea015a06c69e29d11","counter":1,"handler":"App\\Session\\DynamoDbSessionHandler"}%

➜ curl -i -b cookie.txt https://your-lambda-url/session-test
{"message":"Session incremented","session_id":"02b0c08e1ccd5f3ea015a06c69e29d11","counter":2,"handler":"App\\Session\\DynamoDbSessionHandler"}%

➜ curl -i -b cookie.txt https://your-lambda-url/session-test
{"message":"Session incremented","session_id":"02b0c08e1ccd5f3ea015a06c69e29d11","counter":3,"handler":"App\\Session\\DynamoDbSessionHandler"}%
```
## Performance Considerations

### Cold Start Optimization

1. **Memory Allocation**: Using 2GB memory reduces cold start times
2. **Composer Optimization**: `--no-dev --optimize-autoloader` reduces code size
3. **PHP 8.4**: Latest PHP version with JIT compiler support

### DynamoDB Performance

1. **Consistent Reads**: Ensures session consistency at the cost of slightly higher latency
2. **On-Demand Billing**: No capacity planning, automatic scaling
3. **TTL**: Automatic cleanup without scan operations

The serverless model's primary advantage is alignment of costs with actual usage, particularly beneficial for applications with variable or unpredictable traffic patterns. However, actual costs vary significantly based on traffic patterns, request complexity, and specific use cases. It's recommended to use AWS cost estimation tools and monitor actual usage to understand the financial impact for your specific application.

## Security Best Practices

### Session Security

1. **Secure Flag**: Ensures cookies only sent over HTTPS
2. **SameSite**: Protects against CSRF attacks
3. **Regenerate ID**: After authentication to prevent session fixation

```yaml
framework:
    session:
        cookie_httponly: true
        cookie_secure: auto
        cookie_samesite: lax
```

### DynamoDB Permissions

The Lambda function requires minimal permissions:

```typescript
ddb.grantReadWriteData(monolithLambda);
```

This grants only:
- `dynamodb:GetItem`
- `dynamodb:PutItem`
- `dynamodb:DeleteItem`
- `dynamodb:Query`
- `dynamodb:Scan`

No administrative permissions are granted to the Lambda function.

## Limitations

While serverless PHP with DynamoDB sessions offers compelling advantages, it's important to understand the limitations and trade-offs. Here's an honest assessment of where this architecture may not be the best fit:

### 1. Cold Start Latency

**The Reality**: Lambda cold starts can add **1-3 seconds** to the first request after a function has been idle. In practice this occurs for less than 1% of the calls.

**Mitigation Strategies**:
- **Provisioned Concurrency**: Pre-warm Lambda instances to eliminate cold starts (adds ~$15/month per instance)
- **Keep-Warm Pings**: Use CloudWatch Events to invoke functions every 5-10 minutes (adds minimal cost but doesn't help with scaling)
- **Larger Memory Allocation**: We use 2GB memory which provides faster CPUs, reducing cold start duration
- **Optimize Code**: Minimize dependencies, use PHP preloading, optimize autoloader

**When it's acceptable**: Background jobs, internal tools, APIs with relaxed SLAs  
**When it's problematic**: User-facing e-commerce, real-time chat, gaming applications

### 2. Request Timeout Constraints

**The Reality**: Our configuration uses **28 seconds timeout** (API Gateway compatible), though Lambda supports up to **15 minutes**, which Lambda URLs supports.

**Not Suitable For**:
- **Long-running batch jobs**: Data exports, report generation, video processing
- **Large file uploads**: Direct file uploads over 10MB become unreliable
- **Complex data migrations**: Multi-step transformations requiring minutes to complete
- **WebSocket connections**: Not supported by Lambda Function URLs (use API Gateway WebSocket instead)

**Recommended Alternatives**:
- **Keep Lambda URL**: If API Gateway specific features are not needed, we can use custom domain with Lambda URLs and process up to 15 minutes
- **AWS Step Functions**: Orchestrate long-running workflows across multiple Lambda invocations
- **ECS/Fargate**: For truly long-running processes (hours), use containers instead
- **Presigned S3 URLs**: For large file uploads, let clients upload directly to S3
- **SQS + Background Workers**: Offload heavy processing to asynchronous queues

### 3. Session Consistency Edge Cases

**The Reality**: DynamoDB is eventually consistent by default, but we use `ConsistentRead: true` to mitigate this.

**Why We Use ConsistentRead**:
```php
'ConsistentRead' => true,  // Ensures we always get the latest session data
```

**Rare Race Conditions**:
Even with consistent reads, race conditions can occur when:
- **Simultaneous Writes**: User opens multiple tabs, both modify session simultaneously—last write wins
- **Write-then-Read Timing**: Session written in one Lambda, immediately read by another—minimal delay possible
- **Cross-Region Scenarios**: If using Global Tables, replication lag can cause stale reads in remote regions

**Practical Impact**: In 99.9% of cases, consistent reads solve the problem. Edge cases typically affect power users opening many tabs or distributed teams across continents.

**Mitigation**: For critical operations (e.g., payment processing), use DynamoDB conditional expressions to ensure atomic updates and detect conflicts.

### 4. DynamoDB Costs at Scale

DynamoDB's pay-per-request pricing is cost-effective at low-to-moderate traffic but pricing characteristics change at high scale.

#### Assumptions

**DynamoDB**:
- On-demand billing: $1.25 per million reads/writes
- 1KB session item size
- 1 read + 1 write per request

**Redis (ElastiCache)**:
- t4g.medium: $0.037/hr (~$27/month)
- 1 node sufficient for low-medium traffic
- High traffic may require bigger node(s)

#### Cost Table

| Traffic | Requests / Month | DynamoDB Cost | Redis Cost | Notes |
|---------|-----------------|---------------|------------|-------|
| Low     | 1M              | $2.50         | $27        | DynamoDB far cheaper at low traffic |
| Medium  | 10M             | $25           | $27        | Costs roughly similar; DynamoDB slightly lower ops |
| High    | 50M             | $125          | $108 (cache.m5.large 3 nodes) | Redis may become cheaper with large, sustained traffic, but ops complexity rises |

**When Fixed Infrastructure (like Redis/ElastiCache) May Become More Cost-Effective**:
- Sustained high traffic volumes where fixed costs are fully utilized
- Long-lived sessions with more reads than writes
- Advanced caching features needed beyond simple session storage

**Hidden DynamoDB Cost Factors**:
- Consistent reads cost more than eventually consistent reads
- Session writes on every request (even if session data unchanged)
- AWS free tier limitations after 12 months

Start with DynamoDB for simplicity and operational efficiency. Monitor costs monthly as traffic grows. If costs become a concern at high scale, evaluate whether fixed infrastructure or caching optimizations make sense for your specific use case.

---

These limitations are not dealbreakers but they're **trade-offs**. For the right use cases (bursty traffic, cost-sensitive, minimal ops), the benefits far outweigh the drawbacks.

## Conclusion

Building serverless PHP applications doesn't require sacrificing familiar frameworks or patterns. By implementing a custom DynamoDB session handler, we achieve:

- **Truly serverless architecture**: No Redis, no EFS, pure AWS managed services
- **Production-ready session management**: Consistent, scalable, and secure
- **Cost-effective**: Pay only for actual usage
- **Developer-friendly**: Standard Symfony application with minimal modifications
- **Type-safe infrastructure**: AWS CDK with TypeScript
- **Modern PHP**: PHP 8.4 with all latest features
- **Local development**: Docker-compose for local testing

The combination of Bref for Lambda PHP support, Symfony for application framework, and DynamoDB for stateful storage creates a robust, scalable, and maintainable serverless application architecture.

### When Should You Use This Architecture?

**Choose this approach when:**
- Traffic is unpredictable or bursty (blogs, seasonal apps, internal tools)
- Cost optimization matters more than absolute performance
- Zero operational overhead is a priority
- You need automatic scaling without capacity planning

Common use cases are:
- CMS - Blogs, documentation sites, and knowledge bases with infrequent or sporadic traffic, when sudden spikes are scaled automatically and quite periods costs pennies
- Admin Panels and Internal Tools - Dashboard interfaces, internal reporting tools, and back-office applications with sporadic usage patterns. DynamoDB maintains session state without requiring Redis or similar infrastructure.
- Multi-Tenant SaaS Applications - B2B platforms where each tenant has independent traffic patterns. DynamoDB's single-table design efficiently manages sessions across all tenants without cross-tenant interference.
- API Services with Session Requirements - REST APIs that need stateful operations like OAuth flows, multi-step workflows, or temporary data caching. No Redis clusters to maintain, no session cleanup cron jobs to manage. DynamoDB TTL handles everything automatically.
- Seasonal Applications - Event registration systems, holiday campaign sites, tax filing applications, and other time-bound services.
- Microservices Requiring Session State - Distributed systems where individual services need temporary state management across invocations.

**Consider alternatives when:**
- You require consistent sub-100ms response times
- Traffic is predictable and sustained at high levels (>10M requests/month)
- Long-running processes or WebSocket connections are needed

### The Bigger Picture

This implementation demonstrates that **serverless and stateful aren't mutually exclusive**. While serverless advocates often emphasize "stateless functions," real-world applications need state management. The key is choosing the right state storage mechanism, and DynamoDB proves that managed, serverless databases can handle session management as effectively as traditional infrastructure, with far less operational burden.

Whether you're building a content management system, an internal admin panel, or a multi-tenant SaaS application, this architecture provides a production-ready foundation. Start simple, monitor costs and performance, and scale confidently knowing your infrastructure will grow with your application without requiring a dedicated ops team.

## Resources

- [Bref Documentation](https://bref.sh/)
- [Bref CDK Constructs](https://github.com/brefphp/constructs)
- [AsyncAws DynamoDB Client](https://async-aws.com/clients/dynamodb.html)
- [Symfony Session Documentation](https://symfony.com/doc/current/session.html)
- [Lambda Function URLs](https://docs.aws.amazon.com/lambda/latest/dg/lambda-urls.html)
- [DynamoDB Single-Table Design](https://www.alexdebrie.com/posts/dynamodb-single-table/)

## Source Code

The complete source code for this application is available at: [rafaelbernard/serverless-php-with-bref-symfony-and-dynamodb-session-management](https://github.com/rafaelbernard/serverless-php-with-bref-symfony-and-dynamodb-session-management)

For detailed technical implementation notes, test coverage reports, and deployment validation, see [`IMPLEMENTATION_SUMMARY.md`](./IMPLEMENTATION_SUMMARY.md) in the repository. This document covers:
- Complete test suite (112 tests across PHP and CDK)
- Infrastructure validation details
- Code quality metrics
- Deployment procedures and best practices

### 💡 Bonus: Guide to Custom Domain Configuration with Route53

Lambda Function URLs provide a quick way to expose your Lambda function over HTTPS, but the auto-generated URL (e.g., `https://abc123xyz.lambda-url.us-east-1.on.aws/`) isn't branded or memorable. But you can add a Simple CNAME Mapping: Direct Route53 CNAME to Lambda Function URL (easiest, limited SSL control).

This is the **quickest and easiest** method. Just create a CNAME record pointing to your Lambda Function URL. Best for internal tools, prototypes, and non-production environments.

#### Prerequisites

Before configuring custom domains, ensure you have:

1. **Domain registered in Route53** (or another registrar with ability to update nameservers)
2. **Hosted Zone created in Route53** for your domain

#### Implementation with CDK

Here's how to add a custom domain CNAME record pointing to your Lambda Function URL using AWS CDK:

```typescript
import * as route53 from 'aws-cdk-lib/aws-route53';
import * as route53Targets from 'aws-cdk-lib/aws-route53-targets';
import * as lambda from 'aws-cdk-lib/aws-lambda';
import { Construct } from 'constructs';

// Assuming you have a Lambda function with Function URL enabled
const myFunction = new lambda.Function(this, 'MyFunction', {
  // ... function configuration
  functionUrlOptions: {
    authType: lambda.FunctionUrlAuthType.NONE, // or AWS_IAM
  },
});

// Get the hosted zone for your domain
const hostedZone = route53.HostedZone.fromLookup(this, 'HostedZone', {
  domainName: 'yourdomain.com',
});

// Create CNAME record pointing to Lambda URL
new route53.CnameRecord(this, 'LambdaUrlCname', {
  zone: hostedZone,
  recordName: 'api', // Creates api.yourdomain.com
  domainName: cdk.Fn.parseDomainName(myFunction.functionUrl), // Extracts hostname from URL
  ttl: cdk.Duration.minutes(5),
  comment: 'CNAME to Lambda Function URL',
});

// Output the custom domain
new cdk.CfnOutput(this, 'CustomDomainUrl', {
  value: `https://api.yourdomain.com`,
  description: 'Custom domain URL for Lambda function',
});
```

#### Testing Your CNAME Setup

After creating the CNAME record, verify it works:

```bash
# Check DNS propagation
dig api.yourdomain.com

# Test the endpoint
curl -i https://api.yourdomain.com/

# Verify SSL certificate
openssl s_client -connect api.yourdomain.com:443 -servername api.yourdomain.com | grep subject
```

**Expected Results**:
- DNS query returns Lambda Function URL hostname as CNAME target
- HTTP request succeeds with same response as Lambda URL
- SSL certificate shows AWS-managed certificate (not your custom domain)
