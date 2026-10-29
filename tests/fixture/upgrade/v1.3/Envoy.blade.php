@servers(['web' => ['deploy@example.test']])

@task('build', ['on' => 'localhost'])
    npm run build
    npm run build:views
    rsync -avz resources/views/assets/head.blade.php deploy@example.test:/var/www/shop/resources/views/assets/
    rsync -avz public/static/ deploy@example.test:/var/www/shop/public/static/
@endtask

@task('deploy', ['on' => 'web'])
    cd /var/www/shop
    git pull
    composer install --no-dev
    php artisan migrate --force
    php artisan route:cache
    php artisan queue:restart
@endtask
