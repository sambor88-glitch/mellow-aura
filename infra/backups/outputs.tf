output "bucket" {
  description = "BACKUP_AWS_BUCKET"
  value       = aws_s3_bucket.backups.bucket
}

output "region" {
  description = "BACKUP_AWS_REGION"
  value       = var.region
}

output "access_key_id" {
  description = "BACKUP_AWS_ACCESS_KEY_ID"
  value       = aws_iam_access_key.backup.id
}

output "secret_access_key" {
  description = "BACKUP_AWS_SECRET_ACCESS_KEY (terraform output -raw secret_access_key)"
  value       = aws_iam_access_key.backup.secret
  sensitive   = true
}
