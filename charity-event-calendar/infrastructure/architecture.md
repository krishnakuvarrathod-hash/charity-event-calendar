# Cloud Architecture & Request Flow Specification
## Charity Event Calendar - High-Availability Academic Cloud Deployment

---

## 1. Executive Summary & Design Principles

The **Charity Event Calendar** platform is engineered following the **AWS Well-Architected Framework**, emphasizing **Reliability**, **Security**, and **Cost Optimization**.

The application eliminates single points of failure across the web, application, and database tiers by deploying across at least two Availability Zones (AZs) with automated health checks, self-healing Auto Scaling, and secure credential delegation.

---

## 2. Architecture Diagram

```mermaid
flowchart TD
    subgraph Internet
        UserBrowser["Visitor / Organizer / Admin Browser"]
    end

    subgraph AWS_Cloud ["AWS Cloud (Region: us-east-1)"]
        subgraph VPC ["Custom VPC (10.0.0.0/16)"]
            
            subgraph Public_Tier ["Public Subnets (Tier 1)"]
                ALB["Application Load Balancer (ALB)<br/>Internet-facing across AZ-a & AZ-b"]
            end

            subgraph App_Tier ["Application Subnets (Tier 2)"]
                subgraph ASG ["Auto Scaling Group (Min: 1, Desired: 2, Max: 3)"]
                    EC2_A["EC2 Instance (AZ-a)<br/>Apache + PHP 8"]
                    EC2_B["EC2 Instance (AZ-b)<br/>Apache + PHP 8"]
                end
            end

            subgraph Data_Tier ["Database Subnets (Tier 3)"]
                RDS["Amazon RDS MySQL 8.0<br/>Multi-AZ / Single-AZ Private Subnet Group"]
            end
        end

        subgraph IAM_SecOps ["Security & Identity Services"]
            IAM_Role["IAM Instance Role & Profile<br/>(secretsmanager:GetSecretValue)"]
            SecretsManager["AWS Secrets Manager<br/>RDS Host, User, Password, Port"]
        end
    end

    UserBrowser -->|HTTPS / HTTP Port 80| ALB
    ALB -->|Forward Traffic (Port 80)| EC2_A
    ALB -->|Forward Traffic (Port 80)| EC2_B
    ALB -.->|Periodic Health Checks /health.php| EC2_A
    ALB -.->|Periodic Health Checks /health.php| EC2_B

    EC2_A -->|Assume Role via IMDSv2| IAM_Role
    EC2_B -->|Assume Role via IMDSv2| IAM_Role
    IAM_Role -->|Authorize Access| SecretsManager
    SecretsManager -.->|Return JSON DB Credentials (Cached 15m)| EC2_A
    SecretsManager -.->|Return JSON DB Credentials (Cached 15m)| EC2_B

    EC2_A -->|MySQL Query Port 3306| RDS
    EC2_B -->|MySQL Query Port 3306| RDS

    classDef awsBox fill:#ffffff,stroke:#2b7a78,stroke-width:2px;
    classDef compBox fill:#e6f4f1,stroke:#2b7a78,stroke-width:1px;
    classDef secBox fill:#fef2f2,stroke:#fe6b64,stroke-width:1px;
    class UserBrowser,ALB,EC2_A,EC2_B,RDS compBox;
    class IAM_Role,SecretsManager secBox;
```

---

## 3. End-to-End Request Flow Walkthrough

1. **User Request Initiation**:
   A user accesses the website via the Application Load Balancer DNS name (e.g., `http://charity-alb-123456789.us-east-1.elb.amazonaws.com`).
2. **Traffic Distribution (ALB)**:
   The Application Load Balancer intercepts port 80/443 traffic in public subnets and performs round-robin routing to healthy EC2 instances in the Auto Scaling Group.
3. **Application Execution (EC2)**:
   The EC2 instance running Apache + PHP 8 processes the page request (e.g., `events.php` or `event-details.php`).
4. **Credential Retrieval (AWS Secrets Manager & IAM)**:
   - On instance launch or cache expiration (15-minute TTL), `config/aws-secrets.php` queries the EC2 Instance Metadata Service (IMDSv2) to assume its attached IAM Role.
   - It securely requests the secret `charity-calendar/rds-credentials` from AWS Secrets Manager using SigV4 signing.
   - Credentials are never hardcoded in GitHub, `.env`, or PHP source files.
5. **Database Transaction (Amazon RDS MySQL)**:
   - The PHP PDO layer connects to Amazon RDS on port 3306 over the private VPC subnet.
   - For critical workflows (like event registrations), rows are locked (`SELECT ... FOR UPDATE`) to prevent concurrent overbooking.
6. **Self-Healing & Health Monitoring**:
   - The ALB periodically polls `/health.php` every 15 seconds.
   - If an EC2 instance fails or the database becomes unreachable, the health probe fails (`HTTP 503`), causing the ALB to immediately stop sending traffic to that instance while the ASG provisions a healthy replacement.

---

## 4. Multi-Tier Security Group Isolation Matrix

| Security Group | Inbound Rules | Outbound Rules | Purpose |
| :--- | :--- | :--- | :--- |
| **ALB Security Group** (`charity-alb-sg`) | Port 80 & 443 from `0.0.0.0/0` (Internet) | Port 80 to `charity-ec2-sg` | Ingress entry point for public visitors |
| **EC2 Security Group** (`charity-ec2-sg`) | Port 80 **ONLY from `charity-alb-sg`** (No direct internet access) | All traffic to `0.0.0.0/0` (or NAT for package updates) | Isolates application compute |
| **RDS Security Group** (`charity-rds-sg`) | Port 3306 **ONLY from `charity-ec2-sg`** | None | Strictly shields database from public internet |

---

## 5. IAM Policy Configuration (Principle of Least Privilege)

The EC2 IAM Role (`CharityAppEc2Role`) is granted only the minimum required permissions to read its designated database secret:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "AllowSecretsManagerGetSecret",
      "Effect": "Allow",
      "Action": [
        "secretsmanager:GetSecretValue",
        "secretsmanager:DescribeSecret"
      ],
      "Resource": "arn:aws:secretsmanager:us-east-1:*:secret:charity-calendar/rds-credentials-*"
    }
  ]
}
```

---

## 6. AWS Academy & Free Tier Cost Optimization

| Service | Configuration | Estimated Demonstration Cost |
| :--- | :--- | :--- |
| **EC2 Compute** | 2 × `t3.micro` or `t2.micro` | Covered under AWS Free Tier / ~$0.0104/hr |
| **Application Load Balancer** | 1 ALB (2 AZs) | ~$0.0225/hr + $0.008 per LCU |
| **Amazon RDS MySQL** | `db.t3.micro` Single-AZ (20 GB gp2) | ~$0.017/hr (or Free Tier) |
| **AWS Secrets Manager** | 1 Secret | ~$0.40/month (prorated: ~$0.01 for demo day) |
| **Data Transfer** | Intra-VPC private data transfer | Negligible (<$0.05) |
| **Total Estimated 5-Hour Demo Cost** | — | **~$0.35 - $0.75 USD** |

> [!NOTE]
> All resources can be terminated or paused immediately after your college presentation using the cleanup guide provided in the README.
