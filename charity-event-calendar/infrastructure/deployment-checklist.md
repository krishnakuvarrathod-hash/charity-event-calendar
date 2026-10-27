# AWS Deployment & Verification Checklist
## Charity Event Calendar - College Demonstration Project

This checklist guides you through step-by-step verification when deploying on AWS (Standard AWS Account or AWS Academy Learner Lab).

---

## Pre-Deployment Verification (Local / Source Checks)

- [x] **Source Files Validated**: All 20 required PHP pages, CSS, JS, and includes created.
- [x] **Database Schema**: `database/schema.sql` syntax tested (InnoDB, utf8mb4, foreign keys, indexes).
- [x] **Database Seed Data**: `database/seed.sql` tested with idempotent duplicate handling.
- [x] **Automated Tests**: All 26 assertion tests passed in `tests/run-tests.php`.
- [x] **Health Check**: `health.php` responds with JSON (HTTP 200 on healthy connection, HTTP 503 on database disconnect).
- [x] **Credential Isolation**: No production passwords or IAM keys stored in source control or `.env.example`.

---

## AWS Deployment Steps (Console or CloudFormation)

### Phase A: Database Tier (Amazon RDS MySQL)
- [ ] **1. Create DB Subnet Group**:
  - Name: `charity-db-subnet-group`
  - VPC: Select Default VPC or Custom VPC.
  - Subnets: Add subnets across at least 2 Availability Zones (e.g., `us-east-1a`, `us-east-1b`).
- [ ] **2. Create RDS Security Group**:
  - Name: `charity-rds-sg`
  - Inbound rule: Port 3306 from `charity-ec2-sg` (Source Security Group).
- [ ] **3. Provision Amazon RDS MySQL**:
  - Engine: MySQL 8.0.x
  - Template: Free tier (or Dev/Test)
  - DB Instance Class: `db.t3.micro` or `db.t4g.micro`
  - Master Username: `charityadmin`
  - Master Password: Set secure password (record for Secrets Manager).
  - Initial Database Name: `charity_calendar`
  - Public Access: **No** (Private and secure).
  - Wait until status displays: **Available**.
  - Copy the **Endpoint** (e.g., `charity-calendar-rds.cxxxx.us-east-1.rds.amazonaws.com`).

---

### Phase B: Security & Credentials (AWS Secrets Manager & IAM)
- [ ] **4. Store Credentials in Secrets Manager**:
  - Secret Type: *Credentials for Amazon RDS database* (or *Other type of secret*).
  - Secret Name: `charity-calendar/rds-credentials`
  - Key/Value Pairs:
    - `host`: `<your-rds-endpoint>`
    - `port`: `3306`
    - `dbname`: `charity_calendar`
    - `username`: `charityadmin`
    - `password`: `<your-rds-password>`
- [ ] **5. Create EC2 IAM Role & Instance Profile**:
  - Trusted Entity: EC2 (`ec2.amazonaws.com`)
  - Role Name: `CharityAppEc2Role`
  - Policy: Attach policy granting `secretsmanager:GetSecretValue` on `arn:aws:secretsmanager:us-east-1:*:secret:charity-calendar/rds-credentials-*`.
  *(In AWS Academy Learner Lab: You can reuse the pre-configured `LabRole` which already has EC2 and Secrets Manager permissions!)*

---

### Phase C: Load Balancer & Application Tier (ALB & ASG)
- [ ] **6. Create Security Groups**:
  - `charity-alb-sg`: Inbound Port 80 from `0.0.0.0/0`.
  - `charity-ec2-sg`: Inbound Port 80 from `charity-alb-sg`.
- [ ] **7. Create Target Group**:
  - Target Type: `Instances`
  - Name: `charity-tg`
  - Protocol: HTTP (Port 80)
  - Health check path: `/health.php`
  - Health check interval: `15 seconds`
  - Healthy threshold: `2`
- [ ] **8. Create Application Load Balancer**:
  - Scheme: Internet-facing
  - Name: `charity-alb`
  - Subnets: Select 2 public subnets in different AZs.
  - Security Group: `charity-alb-sg`
  - Listener: HTTP:80 forwarding to Target Group `charity-tg`.
- [ ] **9. Create Launch Template**:
  - Template Name: `charity-launch-template`
  - AMI: Amazon Linux 2023 or Ubuntu 22.04 LTS
  - Instance Type: `t3.micro` (or `t2.micro`)
  - IAM Instance Profile: `CharityAppEc2Role` (or `LabRole`)
  - Security Group: `charity-ec2-sg`
  - Advanced Details -> User Data: Paste contents of `scripts/install.sh`.
- [ ] **10. Create Auto Scaling Group**:
  - ASG Name: `charity-asg`
  - Launch Template: Select `charity-launch-template`
  - Subnets: Select subnets across 2 AZs.
  - Load Balancer: Attach to existing target group `charity-tg`.
  - Group size: Min: 1, Desired: 2, Max: 3.
  - Health check grace period: 180 seconds.

---

### Phase D: Database Schema & Seed Import
- [ ] **11. Import Schema and Seed Data**:
  - Connect to one of your running EC2 instances using **EC2 Instance Connect** or **Systems Manager Session Manager**.
  - Run the import command:
    ```bash
    mysql -h <your-rds-endpoint> -u charityadmin -p charity_calendar < /var/www/html/database/schema.sql
    mysql -h <your-rds-endpoint> -u charityadmin -p charity_calendar < /var/www/html/database/seed.sql
    ```
  - Verify seed records:
    ```bash
    mysql -h <your-rds-endpoint> -u charityadmin -p charity_calendar -e "SELECT id, title, status FROM events;"
    ```

---

### Phase E: Live Validation & Verification
- [ ] **12. Test Health Endpoint**:
  - In a browser, open: `http://<your-alb-dns-name>/health.php`
  - Verify response code is `HTTP 200` and JSON contains `"status": "healthy"`, `"database": "connected"`.
- [ ] **13. Test Public Website**:
  - Open: `http://<your-alb-dns-name>/index.php`
  - Verify 6 upcoming approved events appear with badges and images.
- [ ] **14. Test Search & Filter**:
  - Open: `http://<your-alb-dns-name>/events.php`
  - Filter by category ("Food Donation") and confirm instant filtering.
- [ ] **15. Test Organizer Workflow**:
  - Log in as organizer (`sarah@hopefoundation.org` / `Password@123`).
  - Submit a new event -> Confirm status is `pending`.
- [ ] **16. Test Admin Approval Workflow**:
  - Log out, log in as admin (`admin@charitycalendar.org` / `Password@123`).
  - Open `/admin/pending-events.php` -> Click "Approve".
  - Open public homepage -> Confirm the newly approved event is now visible!
- [ ] **17. Test Attendee Registration**:
  - Register as visitor (`john.doe@example.com` / `Password@123`).
  - Open event details -> Click "Register for Event".
  - Verify confirmed attendance and updated capacity count.
