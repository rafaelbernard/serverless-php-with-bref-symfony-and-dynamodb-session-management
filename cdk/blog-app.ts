import { CfnOutput, Stack } from 'aws-cdk-lib';
import { Construct } from 'constructs';
import { BlogAppStatefulStack } from "./stateful/stateful";
import { MyStackProps } from "../bin/cdk";
import { BlogAppStatelessStack } from "./stateless/stateless";

export class BlogApp extends Stack {

  constructor(scope: Construct, id: string, props: MyStackProps) {
    super(scope, id, props);

    const stackPrefix = props.shared.envStackPrefix;
    props.shared.stackPrefix = props.shared.envStackPrefix;

    const statefulStack = new BlogAppStatefulStack(this, `${stackPrefix}-StatefulStack`, props);
    const statelessStack = new BlogAppStatelessStack(this, `${stackPrefix}-StatelessStack`, props, statefulStack);

    // Lambda and API outputs
    new CfnOutput(this, 'Lambda', { value: statelessStack.monolithLambda.functionName });
    new CfnOutput(this, 'LambdaURL', { value: statelessStack.monolithLambdaFunctionUrl.url });

    // Infrastructure outputs
    new CfnOutput(this, 'DynamoDb', { value: statefulStack.ddb.tableName });
    new CfnOutput(this, 'AssetsBucket', { value: statelessStack.staticAssetsBucket.bucketName });
  }
}
