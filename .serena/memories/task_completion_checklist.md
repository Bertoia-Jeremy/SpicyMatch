# SpicyMatch — What to Do When a Task is Completed

1. **PHP style**: `docker exec -w /var/www/html/spicymatch p8.4 composer fix-cs`
2. **Static analysis**: `docker exec -w /var/www/html/spicymatch p8.4 composer phpstan`
3. **If entity changed**: `docker exec -w /var/www/html/spicymatch p8.4 php bin/console doctrine:schema:update --force`
4. **If Twig/CSS classes changed**: `yarn build`
5. **Tests**: `docker exec -w /var/www/html/spicymatch p8.4 php vendor/bin/phpunit --testsuite=Unit`
6. **Commit**: Conventional Commits format (feat/fix/chore/refactor)
