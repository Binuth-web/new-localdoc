# MedConnect – AWS Deployment Guide

> Full PHP + MySQL application hosted on AWS using CloudFormation.  
> Designed for **AWS Free Tier** + **$50 credits** budget.

---

## Architecture Overview

```
Internet
   │
   ▼ (port 80 / 443)
EC2 t2.micro  ──────────►  RDS MySQL db.t3.micro
(Apache + PHP 8.1)          (medconnect_db, 20 GB gp2)
Elastic IP (stable)          Security Group: EC2 only
```

**Monthly cost estimate:**
| Resource | Free Tier | After 12 months |
|---|---|---|
| EC2 t2.micro | 750 hrs/month FREE | ~$8.50/month |
| RDS db.t3.micro | 750 hrs/month FREE | ~$13.00/month |
| Elastic IP | FREE while attached | FREE while attached |
| EBS 8 GB gp2 | 30 GB/month FREE | ~$0.80/month |
| RDS Storage 20 GB | 20 GB FREE | ~$2.30/month |
| **Total** | **~$0.00** | **~$24.60/month** |

---

## Prerequisites

Before deploying, make sure you have:

1. **AWS Account** with the $50 credit applied
2. **AWS CLI** installed and configured
   ```bash
   aws configure
   # Enter: Access Key ID, Secret Access Key, Region (e.g. ap-south-1), Output: json
   ```
3. **EC2 Key Pair** created in your target region
   - AWS Console → EC2 → Key Pairs → Create key pair
   - Download the `.pem` file and save it securely
4. **Git** installed locally (for any local testing)

---

## Deployment Steps

### Step 1 – Clone / navigate to the repo

```bash
git clone https://github.com/Binuth-web/new-localdoc.git
cd new-localdoc
```

### Step 2 – Deploy the CloudFormation stack

```bash
aws cloudformation create-stack \
  --stack-name medconnect-prod \
  --template-body file://aws/cloudformation.yaml \
  --capabilities CAPABILITY_NAMED_IAM \
  --parameters \
    ParameterKey=KeyPairName,ParameterValue=YOUR_KEY_PAIR_NAME \
    ParameterKey=DBPassword,ParameterValue=YourSecurePassword123 \
    ParameterKey=GitHubRepoURL,ParameterValue=https://github.com/Binuth-web/new-localdoc.git \
    ParameterKey=AppBranch,ParameterValue=main
```

> **Replace** `YOUR_KEY_PAIR_NAME` with your actual key pair name and `YourSecurePassword123` with a strong password (no `@`, `/`, `'`, or `"` characters).

### Step 3 – Wait for the stack to complete (~10-15 minutes)

```bash
aws cloudformation wait stack-create-complete --stack-name medconnect-prod
echo "Stack creation complete!"
```

Or monitor in the AWS Console → CloudFormation → Stacks → medconnect-prod → Events.

### Step 4 – Get the outputs

```bash
aws cloudformation describe-stacks \
  --stack-name medconnect-prod \
  --query "Stacks[0].Outputs" \
  --output table
```

Note down:
- `WebsiteURL` – your app's public URL
- `RDSEndpoint` – the database hostname
- `SSHCommand` – the SSH command to connect

### Step 5 – SSH into the EC2 instance and seed the database

The CloudFormation template creates the `medconnect_db` database automatically.  
You need to import the full schema manually:

```bash
# 1. SSH into the server (replace <IP> and <key.pem>)
ssh -i /path/to/your-key.pem ec2-user@<EC2_PUBLIC_IP>

# 2. Check that the bootstrap completed
sudo tail -50 /var/log/userdata.log

# 3. Verify Apache is running
sudo systemctl status httpd

# 4. Connect to RDS and verify the database
mysql -h <RDS_ENDPOINT> -u medconnect_admin -p
# Enter your DBPassword when prompted
# Then inside MySQL:
SHOW DATABASES;
USE medconnect_db;
SHOW TABLES;
exit;

# 5. Import your schema SQL file (add your schema.sql to the repo's database/ folder)
mysql -h <RDS_ENDPOINT> -u medconnect_admin -p medconnect_db \
  < /var/www/html/medconnect/medconnect_db.sql

# 6. Verify the app is working
curl http://localhost/api/get_centers.php
```

### Step 6 – Open the app in your browser

Navigate to the `WebsiteURL` from the stack outputs, e.g.:

```
http://1.2.3.4/index.html          # Landing page
http://1.2.3.4/login.html          # Patient login
http://1.2.3.4/admin_login.html    # Admin login
http://1.2.3.4/staff_login.html    # Staff login
```

---

## Running Tests Against the Live Server

### Python / Selenium Tests

```bash
# Install dependencies
pip install -r aws/requirements.txt

# Update BASE_URL in test_medconnect.py
# Change: BASE_URL = "http://localhost:8000"
# To:     BASE_URL = "http://<EC2_PUBLIC_IP>"

# Run all tests
python test_medconnect.py

# Or with pytest for nicer output
pytest test_medconnect.py -v
```

> **Note:** Selenium tests require **Google Chrome** installed on your machine.  
> `webdriver-manager` will automatically download the matching ChromeDriver.

### JavaScript / Node.js Tests

```bash
npm install
npm test
```

---

## Updating the Application

To pull new code changes onto the server:

```bash
# SSH into the EC2 instance
ssh -i /path/to/key.pem ec2-user@<EC2_PUBLIC_IP>

# Pull latest code
cd /var/www/html/medconnect
sudo git pull origin main

# Restart Apache to clear any opcode cache
sudo systemctl restart httpd
```

---

## Stack Management

### Update the stack (e.g., change DB password)

```bash
aws cloudformation update-stack \
  --stack-name medconnect-prod \
  --template-body file://aws/cloudformation.yaml \
  --capabilities CAPABILITY_NAMED_IAM \
  --parameters \
    ParameterKey=KeyPairName,UsePreviousValue=true \
    ParameterKey=DBPassword,ParameterValue=NewSecurePassword456 \
    ParameterKey=GitHubRepoURL,UsePreviousValue=true \
    ParameterKey=AppBranch,UsePreviousValue=true
```

### Delete the stack (saves costs)

> ⚠️ This will delete the EC2 instance and take a final RDS snapshot.

```bash
aws cloudformation delete-stack --stack-name medconnect-prod
```

---

## Troubleshooting

| Problem | Solution |
|---|---|
| Stack creation fails | Check CloudFormation Events tab for the error |
| App not loading after deploy | SSH in and run: `sudo tail -f /var/log/userdata.log` |
| Database connection failed | Verify RDS security group allows EC2 SG on port 3306 |
| Apache not running | Run: `sudo systemctl restart httpd && sudo systemctl status httpd` |
| PHP errors | Check: `sudo tail -50 /var/log/httpd/medconnect_error.log` |
| Key pair not found | Make sure the Key Pair exists in the **same AWS region** you're deploying to |
| AMI not found for region | Add your region's Amazon Linux 2023 AMI ID to the `Mappings` section in `cloudformation.yaml` |

### Finding your region's Amazon Linux 2023 AMI

```bash
aws ec2 describe-images \
  --owners amazon \
  --filters "Name=name,Values=al2023-ami-2023*" \
              "Name=architecture,Values=x86_64" \
              "Name=state,Values=available" \
  --query "sort_by(Images, &CreationDate)[-1].ImageId" \
  --output text
```

---

## Security Hardening (Post-Launch)

Once the app is working, consider these improvements:

1. **Restrict SSH** – Update `WebServerSG` to allow port 22 from your IP only:
   ```
   CidrIp: YOUR.IP.ADDRESS/32
   ```
2. **Add HTTPS** – Use AWS Certificate Manager (ACM) + an Application Load Balancer (adds ~$16/month), or install Certbot (Let's Encrypt) on the EC2 directly for free.
3. **Use AWS Secrets Manager** – Store the DB password in Secrets Manager instead of a CloudFormation parameter.
4. **Enable RDS encryption** – Add `StorageEncrypted: true` to the RDS resource.

---

## Files in this `aws/` Directory

| File | Description |
|---|---|
| `cloudformation.yaml` | Complete AWS infrastructure template |
| `requirements.txt` | Python dependencies for the Selenium test suite |
| `README.md` | This deployment guide |
