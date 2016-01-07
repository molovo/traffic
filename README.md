# Traffic

A simple router in ~200 lines of PHP.

```
use Molovo\Traffic\Router;

Router::get('/hello', function() {
    echo 'Hello World!';
});
```

#### Installing

```
composer require "molovo/traffic"
```

#### Usage

```
// GET /news/2
Router::get('/news/{page:int}', function($page) {
    echo $page; // 2
});

// POST /user/hi@molovo.co
Router::get('/user/{email:email}', function($email) {
    echo $email; // hi@molovo.co
});
```
