# Kopie zapasowe (MA-79)

Co noc o 02:50 `backup:run` pakuje zrzut bazy oraz `storage/app/public` (zdjęcia) i `storage/app/private`
(certyfikaty, oświadczenia, pliki od klientek) do archiwum zaszyfrowanego hasłem (AES-256) i wysyła je do S3
we Frankfurcie. O 02:40 `backup:clean` usuwa stare archiwa, a o 08:50 `backup:monitor` sprawdza, czy nocna kopia
istnieje. W poniedziałek o 04:20 `monitoring:verify-backup` odtwarza najnowszą kopię w osobnej bazie
i sprawdza tabele oraz zdjęcia. Każdy problem to alert na `MONITORING_ALERT_EMAIL`.

Zostaje: 7 dni wszystkich kopii, 16 dni dziennych, 8 tygodni tygodniowych, 4 miesiące miesięcznych, 2 lata rocznych.
Usunięte archiwum da się jeszcze odzyskać z wersji w S3 przez 30 dni.

## Wdrożenie

1. Utwórz bucket i klucz (AWS, eu-central-1):

   ```bash
   cd infra/backups
   terraform init
   terraform apply            # nazwa bucketa: -var bucket_name=… jeśli mellowaura-backups jest zajęta
   terraform output
   terraform output -raw secret_access_key
   ```

   Plik `terraform.tfstate` zawiera klucz. Nie commituj go (jest w `.gitignore`) i trzymaj w bezpiecznym miejscu.

2. Na Forge utwórz pustą bazę `mellowaura_restore` dla tego samego użytkownika co baza sklepu.

3. Na Forge (Environment) ustaw:

   ```dotenv
   BACKUP_DISK=backups
   BACKUP_ARCHIVE_PASSWORD=<długie losowe hasło, zapisz też poza serwerem>
   BACKUP_AWS_ACCESS_KEY_ID=<terraform output access_key_id>
   BACKUP_AWS_SECRET_ACCESS_KEY=<terraform output -raw secret_access_key>
   BACKUP_AWS_REGION=eu-central-1
   BACKUP_AWS_BUCKET=<terraform output bucket>
   DB_RESTORE_DATABASE=mellowaura_restore
   ```

   Bez hasła archiwum nie da się otworzyć. Zgubione hasło = bezużyteczne kopie, więc trzymaj je też poza serwerem.

4. Sprawdź na serwerze jako użytkownik `forge`:

   ```bash
   php artisan backup:run
   php artisan backup:list
   php artisan monitoring:verify-backup
   ```

## Odtworzenie po awarii

1. Pobierz archiwum z S3 (konsola AWS albo `aws s3 cp s3://<bucket>/mellowaura-production/<data>.zip .`).
2. Rozpakuj z hasłem: `7z x <data>.zip` (zwykły `unzip` nie obsługuje AES-256).
3. Baza: `mysql -u forge -p <baza> < db-dumps/mysql-<baza>.sql`.
4. Pliki: skopiuj `public/` do `storage/app/public/` i `private/` do `storage/app/private/`, potem `php artisan storage:link`.
5. Miniatury zdjęć, jeśli trzeba: `php artisan media-library:regenerate`.

## Koszt

Około 1–3 USD miesięcznie przy 1–2 GB zdjęć: ~30 archiwów w retencji plus wersje z 30 dni, S3 Standard w eu-central-1.
