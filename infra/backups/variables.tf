variable "region" {
  description = "AWS region for the bucket."
  type        = string
  default     = "eu-central-1"
}

variable "bucket_name" {
  description = "Globally unique bucket name."
  type        = string
  default     = "mellowaura-backups"
}

variable "noncurrent_days" {
  description = "Days a deleted or overwritten archive is still recoverable."
  type        = number
  default     = 30
}
