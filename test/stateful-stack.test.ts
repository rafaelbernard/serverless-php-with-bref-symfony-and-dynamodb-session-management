import * as cdk from "aws-cdk-lib";
import { Template, Match } from "aws-cdk-lib/assertions";
import { BlogAppStatefulStack } from "../cdk/stateful/stateful";

describe("BlogAppStatefulStack", () => {
  beforeAll(() => {
    process.env.NODE_ENV = "sandbox";
  });

  let template: Template;

  beforeEach(() => {
    const app = new cdk.App();
    const parentStack = new cdk.Stack(app, "ParentStack");

    const statefulStack = new BlogAppStatefulStack(parentStack, "TestStatefulStack", {
      shared: {
        environment: "sandbox",
        stackPrefix: "TestBlogApp",
        envStackPrefix: "sandbox-TestBlogApp",
        hostedZoneId: "Z1234567890ABC",
        zoneName: "example.com",
        appDomainName: "test.example.com",
      },
    });

    template = Template.fromStack(statefulStack);
  });

  it("should create a DynamoDB table", () => {
    template.resourceCountIs("AWS::DynamoDB::Table", 1);
  });

  it("should configure DynamoDB table with correct key schema", () => {
    template.hasResourceProperties("AWS::DynamoDB::Table", {
      AttributeDefinitions: [
        { AttributeName: "PK", AttributeType: "S" },
        { AttributeName: "SK", AttributeType: "S" },
      ],
      KeySchema: [
        { AttributeName: "PK", KeyType: "HASH" },
        { AttributeName: "SK", KeyType: "RANGE" },
      ],
    });
  });

  it("should use PAY_PER_REQUEST billing mode", () => {
    template.hasResourceProperties("AWS::DynamoDB::Table", {
      BillingMode: "PAY_PER_REQUEST",
    });
  });

  it("should enable TTL on expiresAt attribute", () => {
    template.hasResourceProperties("AWS::DynamoDB::Table", {
      TimeToLiveSpecification: {
        AttributeName: "expiresAt",
        Enabled: true,
      },
    });
  });

  it("should have deletion protection disabled for sandbox environment", () => {
    template.hasResourceProperties("AWS::DynamoDB::Table", {
      DeletionProtectionEnabled: false,
    });
  });

  it("should have deletion protection enabled for prod environment", () => {
    const app = new cdk.App();
    const parentStack = new cdk.Stack(app, "ParentStack");

    const prodStatefulStack = new BlogAppStatefulStack(parentStack, "TestStatefulStack", {
      shared: {
        environment: "prod",
        stackPrefix: "TestBlogApp",
        envStackPrefix: "prod-TestBlogApp",
        hostedZoneId: "Z1234567890ABC",
        zoneName: "example.com",
        appDomainName: "prod.example.com",
      },
    });

    const prodTemplate = Template.fromStack(prodStatefulStack);

    prodTemplate.hasResourceProperties("AWS::DynamoDB::Table", {
      DeletionProtectionEnabled: true,
    });
  });

  it("should name the table correctly", () => {
    template.hasResourceProperties("AWS::DynamoDB::Table", {
      TableName: "TestStatefulStack-table",
    });
  });
});
