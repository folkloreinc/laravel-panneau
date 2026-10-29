@servers(['web' => ['deploy@example.test'], 'localhost' => 'localhost'])

@setup
    $path = '/var/www/example';
    $staticPaths = [
        'public/static/',
        'resources/views/assets/head.blade.php',
        'resources/views/assets/body.blade.php',
    ];
@endsetup

@story('deploy')
    build
    sync-static
    pull
@endstory

@task('build', ['on' => 'localhost'])
    npm run build
@endtask

@task('sync-static', ['on' => 'localhost'])
    @foreach ($staticPaths as $staticPath)
        rsync -avz {{ $staticPath }} deploy@example.test:{{ $path }}/{{ $staticPath }}
    @endforeach
@endtask

@task('pull', ['on' => 'web'])
    cd {{ $path }}
    git pull origin main
    composer install --no-dev
    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
@endtask
