import { Duration, Fn, NestedStack, RemovalPolicy, Size } from "aws-cdk-lib";
import { Construct } from "constructs";
import { MyNestedStackProps } from "../../bin/cdk";
import * as s3 from "aws-cdk-lib/aws-s3";
import { Bucket } from "aws-cdk-lib/aws-s3";
import * as s3deploy from "aws-cdk-lib/aws-s3-deployment";
import { packagePhpCode, PhpFpmFunction } from "@bref.sh/constructs";
import * as lambda from "aws-cdk-lib/aws-lambda";
import { FunctionUrl } from "aws-cdk-lib/aws-lambda";
import * as ddb from "aws-cdk-lib/aws-dynamodb";
import { BlogAppStatefulStack } from "../stateful/stateful";

export class BlogAppStatelessStack extends NestedStack {
  public readonly monolithLambda: PhpFpmFunction;
  public staticAssetsBucket: s3.Bucket;
  public monolithLambdaFunctionUrl: FunctionUrl;
  public functionUrlHostname: string;

  constructor(scope: Construct, id: string, props: MyNestedStackProps, statefulStack: BlogAppStatefulStack) {
    super(scope, id, props);
    this.staticAssetsBucket = this.createS3BucketAndDeployment(props);

    const {
      monolithLambda,
      monolithLambdaFunctionUrl
    } = this.createLambda(props, this.staticAssetsBucket, statefulStack.ddb);
    this.monolithLambda = monolithLambda;
    this.monolithLambdaFunctionUrl = monolithLambdaFunctionUrl;

    this.functionUrlHostname = Fn.parseDomainName(this.monolithLambdaFunctionUrl.url);
  }

  private createS3BucketAndDeployment(props: MyNestedStackProps) {
    const staticAssetsBucket = new s3.Bucket(this, `AssetsBucket`, {
      publicReadAccess: true,
      blockPublicAccess: s3.BlockPublicAccess.BLOCK_ACLS_ONLY,
      autoDeleteObjects: props.shared.environment !== 'prod',
      removalPolicy: props.shared.environment !== 'prod' ? RemovalPolicy.DESTROY : RemovalPolicy.RETAIN,
    });

    new s3deploy.BucketDeployment(this, `${props.shared.stackPrefix}-AssetsDeployment`, {
      sources: [s3deploy.Source.asset('php/public/build')],
      destinationBucket: staticAssetsBucket,
      destinationKeyPrefix: 'build/',
    });

    return staticAssetsBucket;
  }

  private createLambda(props: MyNestedStackProps, staticAssetsBucket: Bucket, ddb: ddb.Table) {
    // Generate a stable APP_SECRET based on year, month and environment
    const date = new Date();
    const yearMonth = `${date.getFullYear()}${(date.getMonth() + 1).toString().padStart(2, '0')}`;
    const secretBase = `${props.shared.environment}-${yearMonth}-${props.shared.stackPrefix}-app-secret`;
    const appSecret = Buffer.from(secretBase).toString('base64');

    const lambdaEnvironment = {
      APP_ENV: props.shared.environment,
      APP_SECRET: appSecret,
      ASSET_URL: `https://${staticAssetsBucket.bucketDomainName}/`,
      AWS_LAMBDA_LOG_FORMAT: 'text',
      BOOK_TABLE_NAME: `${props.shared.stackPrefix}-StatefulStack-table`,
    };

    const monolithFunction = 'App';
    const monolithLambda = new PhpFpmFunction(this, monolithFunction, {
      handler: 'public/index.php',
      phpVersion: '8.4',
      code: packagePhpCode('php', {
        exclude: [
          '.env.local',
          'bin/',
        ],
      }),
      functionName: `${props.shared.stackPrefix}-${monolithFunction}`,
      timeout: Duration.seconds(28),
      memorySize: Size.gibibytes(2).toMebibytes(),
      environment: lambdaEnvironment,
    });

    const monolithLambdaFunctionUrl = monolithLambda.addFunctionUrl({ authType: lambda.FunctionUrlAuthType.NONE });

    ddb.grantReadWriteData(monolithLambda);

    return { monolithLambda, monolithLambdaFunctionUrl };
  }
}
