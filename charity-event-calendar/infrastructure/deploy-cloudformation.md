# Deploy with CloudFormation

This stack creates an internet-facing Application Load Balancer, two EC2 application instances in an Auto Scaling group, a private RDS MySQL database, a Secrets Manager secret, and the least-privilege IAM role the instances require.

## Before you create the stack

1. Push this project to a **public** Git repository. The stack clones this repository when it creates each EC2 instance.
2. In the AWS console, select a region and identify one VPC plus two subnets in different Availability Zones. For a default VPC, select its two public subnets so the ALB and EC2 instances have outbound internet access for the initial Git clone and package installation.
3. Choose a database password that is at least 8 characters. Do not reuse a personal or AWS password.

## Console deployment

1. Open **CloudFormation** and select **Create stack** → **With new resources (standard)**.
2. Upload `infrastructure/cloudformation-template.yaml`.
3. Name the stack, for example `charity-calendar-demo`.
4. Provide `VpcId`, two `SubnetIds`, `DbUsername`, `DbPassword`, and the public `RepositoryUrl` ending in `.git`.
5. At the capabilities page, select **I acknowledge that AWS CloudFormation might create IAM resources with custom names**.
6. Create the stack and wait for `CREATE_COMPLETE` (RDS commonly makes this take several minutes).
7. Open the stack **Outputs** tab and visit `ApplicationUrl`. Confirm `ApplicationUrl/health.php` returns HTTP 200 before testing the site.

## Command-line alternative

With AWS CLI credentials already configured, run this from the repository root. Replace the placeholder values; do not put the database password in a committed file.

```powershell
aws cloudformation deploy --stack-name charity-calendar-demo --template-file infrastructure/cloudformation-template.yaml --capabilities CAPABILITY_NAMED_IAM --parameter-overrides VpcId=vpc-REPLACE SubnetIds="subnet-ONE,subnet-TWO" DbUsername=charityadmin DbPassword='REPLACE_WITH_A_STRONG_PASSWORD' RepositoryUrl=https://github.com/ACCOUNT/charity-event-calendar.git
```

Then retrieve the public endpoint:

```powershell
aws cloudformation describe-stacks --stack-name charity-calendar-demo --query "Stacks[0].Outputs[?OutputKey=='ApplicationUrl'].OutputValue" --output text
```

## Notes

- The database schema and demo seed data are imported automatically during instance bootstrap. The SQL is idempotent, so replacement instances do not create duplicate seed rows.
- The stack deliberately leaves the application HTTP-only for a simple academic deployment. Before any real-world use, add an ACM certificate, HTTPS listener, HTTP-to-HTTPS redirect, and set `SESSION_SECURE_COOKIE=true`.
- An ALB and RDS are paid services in most regions. Delete the stack after the demonstration; this template is configured to remove the DB without a final snapshot, so export data first if you need to retain it.
