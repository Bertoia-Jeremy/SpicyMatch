# SpicyMatch — Suggested Commands

## PHP (via Docker container `p8.4`)
```bash
# Style check / fix
docker exec -w /var/www/html/spicymatch p8.4 composer check-cs
docker exec -w /var/www/html/spicymatch p8.4 composer fix-cs

# Static analysis
docker exec -w /var/www/html/spicymatch p8.4 composer phpstan

# Rector refactoring
docker exec -w /var/www/html/spicymatch p8.4 composer rector-dry
docker exec -w /var/www/html/spicymatch p8.4 composer rector

# Tests
docker exec -w /var/www/html/spicymatch p8.4 php vendor/bin/phpunit --testsuite=Unit
docker exec -w /var/www/html/spicymatch p8.4 php vendor/bin/phpunit --testsuite=Integration

# Doctrine
docker exec -w /var/www/html/spicymatch p8.4 php bin/console doctrine:schema:update --force
docker exec -w /var/www/html/spicymatch p8.4 php bin/console doctrine:fixtures:load --append --group=GroupName
```

## Frontend (from /home/jbertoia/docker/volumes/www/spicymatch)
```bash
yarn dev      # watch Tailwind CSS
yarn build    # build minified Tailwind CSS
```

## Symfony Console
```bash
docker exec docker_php-8.4 php /var/www/html/spicymatch/bin/console <command>
```
