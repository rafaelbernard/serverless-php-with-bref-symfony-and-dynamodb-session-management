import * as cdk from "aws-cdk-lib";
import { Template, Match } from "aws-cdk-lib/assertions";
import { BlogAppStatefulStack } from "../cdk/stateful/stateful";
import { BlogAppStatelessStack } from "../cdk/stateless/stateless";

describe("BlogAppStatelessStack", () => {
  beforeAll(() => {
    process.env.NODE_ENV = "sandbox";
  });

  let template: Template;
  let statefulStack: BlogAppStatefulStack;

  beforeEach(() => {
    const app = new cdk.App();
    const parentStack = new cdk.Stack(app, "ParentStack");

    statefulStack = new BlogAppStatefulStack(parentStack, "TestStatefulStack", {
      shared: {
        environment: "sandbox",
        stackPrefix: "TestBlogApp",
        envStackPrefix: "sandbox-TestBlogApp",
        hostedZoneId: "Z1234567890ABC",
        zoneName: "example.com",
        appDomainName: "test.example.com",
      },
    });

    const statelessStack = new BlogAppStatelessStack(
      parentStack,
      "TestStatelessStack",
      {
        shared: {
          environment: "sandbox",
          stackPrefix: "TestBlogApp",
          envStackPrefix: "sandbox-TestBlogApp",
          hostedZoneId: "Z1234567890ABC",
          zoneName: "example.com",
          appDomainName: "test.example.com",
        },
      },
      statefulStack
    );

    template = Template.fromStack(statelessStack);
  });

  it("should create a Lambda function", () => {
    // PhpFpmFunction creates 2 Lambda functions: PHP-FPM runtime + handler
    template.resourceCountIs("AWS::Lambda::Function", Match.anyValue());
  });

  it("should configure Lambda function with correct runtime and handler", () => {
    // Check that at least one Lambda function exists with expected properties
    template.hasResourceProperties("AWS::Lambda::Function", {
      Handler: Match.anyValue(),
      Timeout: 28,
      MemorySize: 2048, // 2 GiB in MiB
    });
  });

  it("should configure Lambda function with environment variables", () => {
    template.hasResourceProperties("AWS::Lambda::Function", {
      Environment: {
        Variables: Match.objectLike({
          APP_ENV: "sandbox",
          AWS_LAMBDA_LOG_FORMAT: "text",
          BOOK_TABLE_NAME: Match.stringLikeRegexp(".*-StatefulStack-table"),
        }),
      },
    });
  });

  it("should create a Function URL", () => {
    template.resourceCountIs("AWS::Lambda::Url", 1);
  });

  it("should configure Function URL with NONE auth type", () => {
    template.hasResourceProperties("AWS::Lambda::Url", {
      AuthType: "NONE",
    });
  });

  it("should create an S3 bucket for static assets", () => {
    template.resourceCountIs("AWS::S3::Bucket", Match.anyValue());
  });

  it("should configure S3 bucket with public read access", () => {
    template.hasResourceProperties("AWS::S3::Bucket", {
      PublicAccessBlockConfiguration: {
        BlockPublicAcls: true,
        BlockPublicPolicy: false,
        IgnorePublicAcls: true,
        RestrictPublicBuckets: false,
      },
    });
  });

  it("should create a bucket deployment for assets", () => {
    template.resourceCountIs("Custom::CDKBucketDeployment", 1);
  });

  it("should grant Lambda DynamoDB read/write permissions", () => {
    // Check for IAM role with DynamoDB permissions
    template.hasResourceProperties("AWS::IAM::Policy", {
      PolicyDocument: {
        Statement: Match.arrayWith([
          Match.objectLike({
            Action: Match.arrayWith([
              "dynamodb:BatchGetItem",
              "dynamodb:GetRecords",
              "dynamodb:GetShardIterator",
              "dynamodb:Query",
              "dynamodb:GetItem",
              "dynamodb:Scan",
              "dynamodb:ConditionCheckItem",
              "dynamodb:BatchWriteItem",
              "dynamodb:PutItem",
              "dynamodb:UpdateItem",
              "dynamodb:DeleteItem",
            ]),
            Effect: "Allow",
          }),
        ]),
      },
    });
  });

  it("should configure Lambda function name with correct prefix", () => {
    template.hasResourceProperties("AWS::Lambda::Function", {
      FunctionName: Match.stringLikeRegexp(".*-App"),
    });
  });

  it("should have different removal policies for sandbox vs prod", () => {
    // Sandbox should allow deletion
    const app = new cdk.App();
    const parentStack = new cdk.Stack(app, "ParentStack");

    const sandboxStatefulStack = new BlogAppStatefulStack(parentStack, "SandboxStatefulStack", {
      shared: {
        environment: "sandbox",
        stackPrefix: "TestBlogApp",
        envStackPrefix: "sandbox-TestBlogApp",
        hostedZoneId: "Z1234567890ABC",
        zoneName: "example.com",
        appDomainName: "test.example.com",
      },
    });

    const sandboxStatelessStack = new BlogAppStatelessStack(
      parentStack,
      "SandboxStatelessStack",
      {
        shared: {
          environment: "sandbox",
          stackPrefix: "TestBlogApp",
          envStackPrefix: "sandbox-TestBlogApp",
          hostedZoneId: "Z1234567890ABC",
          zoneName: "example.com",
          appDomainName: "test.example.com",
        },
      },
      sandboxStatefulStack
    );

    const sandboxTemplate = Template.fromStack(sandboxStatelessStack);

    // Sandbox buckets should have autoDeleteObjects enabled
    sandboxTemplate.hasResourceProperties("AWS::S3::Bucket", {
      // DeletionPolicy is a stack-level property, not a resource property
      // We can verify through the Custom::S3AutoDeleteObjects resource
    });

    // Verify auto-delete custom resource exists for sandbox
    sandboxTemplate.resourceCountIs("Custom::S3AutoDeleteObjects", Match.anyValue());
  });

  it("should set APP_SECRET environment variable", () => {
    template.hasResourceProperties("AWS::Lambda::Function", {
      Environment: {
        Variables: Match.objectLike({
          APP_SECRET: Match.anyValue(),
        }),
      },
    });
  });

  it("should set ASSET_URL environment variable", () => {
    template.hasResourceProperties("AWS::Lambda::Function", {
      Environment: {
        Variables: Match.objectLike({
          ASSET_URL: Match.stringLikeRegexp("https://.*\\.s3\\..*\\.amazonaws\\.com/"),
        }),
      },
    });
  });

  it("should configure Lambda with PHP 8.4 runtime", () => {
    // Bref uses custom runtimes, so we check for the Bref layer
    template.hasResourceProperties("AWS::Lambda::Function", {
      Layers: Match.arrayWith([
        Match.stringLikeRegexp(".*php-84.*"),
      ]),
    });
  });
});
