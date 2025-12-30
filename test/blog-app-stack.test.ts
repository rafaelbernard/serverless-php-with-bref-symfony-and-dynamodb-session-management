import * as cdk from "aws-cdk-lib";
import { Template } from "aws-cdk-lib/assertions";
import { BlogApp } from "../cdk/blog-app";

describe("BlogApp Stack", () => {
  beforeAll(() => {
    // Set required environment variable for config
    process.env.NODE_ENV = "sandbox";
  });

  it("should create stack without errors", () => {
    const app = new cdk.App();

    // WHEN - Create the stack
    const stack = new BlogApp(app, "TestBlogApp", {
      env: { region: "us-east-1" },
      stackName: "test-blog-app-sandbox",
      shared: {
        environment: "sandbox",
        stackPrefix: "TestBlogApp",
        envStackPrefix: "sandbox-TestBlogApp",
        hostedZoneId: "Z1234567890ABC",
        zoneName: "example.com",
        appDomainName: "test.example.com",
      },
    });

    // THEN - Stack should be created
    expect(stack).toBeDefined();
    
    const template = Template.fromStack(stack);
    
    // Verify nested stacks are created
    template.resourceCountIs("AWS::CloudFormation::Stack", 2);
  });

  it("should output Lambda function name, URL, and DynamoDB table", () => {
    const app = new cdk.App();

    const stack = new BlogApp(app, "TestBlogApp", {
      env: { region: "us-east-1" },
      stackName: "test-blog-app-sandbox",
      shared: {
        environment: "sandbox",
        stackPrefix: "TestBlogApp",
        envStackPrefix: "sandbox-TestBlogApp",
        hostedZoneId: "Z1234567890ABC",
        zoneName: "example.com",
        appDomainName: "test.example.com",
      },
    });

    const template = Template.fromStack(stack);

    // Verify outputs exist
    template.hasOutput("Lambda", {});
    template.hasOutput("LambdaURL", {});
    template.hasOutput("DynamoDb", {});
    template.hasOutput("AssetsBucket", {});
  });
});
