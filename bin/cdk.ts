#!/usr/bin/env node
import * as cdk from 'aws-cdk-lib';
import config from 'config';
import { BlogApp } from "../cdk/blog-app";
import { NestedStackProps, StackProps } from "aws-cdk-lib";

export const StackMetadata = {
  id: 'BlogApp',
  slug: 'blog-app',
}

const environment = config.get<string>('cdk.environment');

export interface Shared {
  environment: string;
  stackPrefix: string;
  envStackPrefix: string;
  hostedZoneId: string;
  zoneName: string;
  appDomainName: string;
}

export interface MyStackProps extends StackProps {
  shared: Shared,
}

export interface MyNestedStackProps extends NestedStackProps {
  shared: Shared,
}

const app = new cdk.App();
new BlogApp(app, StackMetadata.id, {
  /* If you don't specify 'env', this stack will be environment-agnostic.
   * Account/Region-dependent features and context lookups will not work,
   * but a single synthesized template can be deployed anywhere. */

  /* Uncomment the next line to specialize this stack for the AWS Account
   * and Region that are implied by the current CLI configuration. */
  // env: { account: process.env.CDK_DEFAULT_ACCOUNT, region: process.env.CDK_DEFAULT_REGION },
  env: { region: 'us-east-1' },

  /* Uncomment the next line if you know exactly what Account and Region you
   * want to deploy the stack to. */
  // env: { account: '123456789012', region: 'us-east-1' },

  /* For more information, see https://docs.aws.amazon.com/cdk/latest/guide/environments.html */
  stackName: `${environment}-${StackMetadata.slug}`,

  shared: {
    environment: environment,
    stackPrefix: StackMetadata.id,
    envStackPrefix: `${environment}-${StackMetadata.id}`,
    hostedZoneId: config.get<string>('cdk.hostedZoneId'),
    zoneName: config.get<string>('cdk.zoneName'),
    appDomainName: config.get<string>('cdk.appDomainName'),
  },
});
