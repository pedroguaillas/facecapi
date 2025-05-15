# Inicializar el ambiente de trabajo

## Generar la key
Ingresar a http://localhost:8005/key
Copiar y pegar la key a APP_KEY

## Generar la jwt:secret
`docker exec -it lumen_app bash`
`php artisan jwt:secret`

# Import DB in Docker

## Dar permisos al usuario
`docker exec -it mysql_db mysql -u root -p`
`GRANT SUPER ON *.* TO 'lumen_user'@'%';`
`FLUSH PRIVILEGES;`

## Importa db en Docker
`docker exec -i mysql_db mysql -u lumen_user -plumen_password lumen_db < /Users/peterdev/Documents/Restore/facecdb.sql`

## Revocar los permisos
`REVOKE SUPER ON *.* FROM 'lumen_user'@'%';`
`FLUSH PRIVILEGES;`