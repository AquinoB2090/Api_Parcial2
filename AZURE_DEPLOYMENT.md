# Azure App Service deployment

Use Azure App Service on Linux with PHP 8.2 or newer.

## Startup command

Set this startup command in the App Service configuration:

```bash
cp /home/site/wwwroot/default /etc/nginx/sites-available/default && service nginx reload
```

This command copies the repository NGINX config, changes the site root to Laravel's `public/` directory, and keeps Laravel routes working.

You can also use the included script if you want extra diagnostics:

```bash
bash /home/site/wwwroot/startup.sh
```

If Azure still shows `404 not found nginx`, open App Service > Advanced Tools > SSH and run:

```bash
bash /home/site/wwwroot/startup.sh
grep -n "root\\|try_files" /etc/nginx/sites-available/default
```

The output should show:

```text
root /home/site/wwwroot/public;
try_files $uri $uri/ /index.php?$args;
```

## Application settings

Add these values in App Service > Settings > Environment variables:

```text
APP_ENV=production
APP_DEBUG=false
APP_KEY=<copy the local APP_KEY value>
APP_URL=https://<your-app-name>.azurewebsites.net

DB_CONNECTION=sqlsrv
DB_HOST=tcp:septiemrbe.database.windows.net
DB_PORT=1433
DB_DATABASE=Parcial2
DB_USERNAME=Brandon
DB_PASSWORD=<database password>
DB_ENCRYPT=yes
DB_TRUST_SERVER_CERTIFICATE=false
DB_LOGIN_TIMEOUT=30

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

## Checks

- Enable build automation so App Service runs Composer during deployment, or deploy with the `vendor/` folder included.
- Confirm the App Service PHP runtime has `pdo_sqlsrv` enabled. If `/api/prueba` returns "could not find driver", the SQL Server PHP driver is missing.
- Confirm the Azure SQL user and password are valid. A "Login failed for user" response means the API reached Azure SQL, but authentication failed.
