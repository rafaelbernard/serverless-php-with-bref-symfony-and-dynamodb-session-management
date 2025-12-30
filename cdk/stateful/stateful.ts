import { NestedStack } from "aws-cdk-lib";
import { Construct } from "constructs";
import * as ddb from "aws-cdk-lib/aws-dynamodb";
import { MyNestedStackProps } from "../../bin/cdk";

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
