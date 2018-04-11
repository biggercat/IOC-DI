<?php
// 它将被以单例模式注入 全局的所有注入点都使用的同一实例
class Foo
{
    public $msg = "foo nothing to say!";

    public function index()
    {
        $this->msg = "foo hello, modified by index method!";
    }
}

// 它将以普通依赖模式注入 各注入点会分别获取一个实例
class Bar
{
    public $msg = "bar nothing to say!";

    public function index()
    {
        $this->msg = "bar hello, modified by index method!";
    }
}

// 契约注入 存储服务契约者
interface StorageEngine
{
    public function info();
}

// 契约实现 存储服务提供者
class FileStorageEngine implements StorageEngine
{
    public $msg = "file storage engine!" . PHP_EOL;

    public function info()
    {
        $this->msg =  "file storage engine!" . PHP_EOL;
    }
}

// 契约实现 存储服务提供者
class RedisStorageEngine implements StorageEngine
{
    public $msg = "redis storage engine!" . PHP_EOL;

    public function info()
    {
        $this->msg =  "redis storage engine!" . PHP_EOL;
    }
}


// 具体的运行类 比如某控制器
class BigCatController
{
    public $foo;
    public $bar;

    // 这里自动注入一次 Foo 和 Bar 的实例
    public function __construct(Foo $foo, Bar $bar)
    {
        $this->foo = $foo;
        $this->bar = $bar;
    }

    // 这里的参数你完全可以乱序的定义（我故意写的很乱序），你只需保证 route 参数中存在对应的必要参数即可
    // 默认值参数可以直接省略
    public function index($name = "big cat", Foo $foo, $sex = 'male', $age, Bar $bar, StorageEngine $se)
    {
        // Foo 为单例模式注入 $this->foo $foo 是同一实例
        $this->foo->index();
        echo $this->foo->msg . PHP_EOL;
        echo $foo->msg . PHP_EOL;
        echo "------------------------------" . PHP_EOL;

        // Bar 为普通模式注入 $this->bar $bar 为两个不同的 Bar 的实例
        $this->bar->index();
        echo $this->bar->msg . PHP_EOL;
        echo $bar->msg . PHP_EOL;
        echo "------------------------------" . PHP_EOL;

        // 契约注入 具体看你为契约者绑定了哪个具体的实现类
        // 我们绑定的 RedisStorageEngine 所以这里注入的是 RedisStorageEngine 的实例
        $se->info();
        echo $se->msg;
        echo "------------------------------" . PHP_EOL;

        // 返回个值
        return "name " . $name . ', age ' . $age . ', sex ' . $sex . PHP_EOL;
    }
}

// 运行

// 路由信息很 MVC 吧
$route = [
    'controller' => BigCatController::class, // 运行的类
    'action'     => 'index', // 运行的方法
    'params'     => [ // 运行的参数
        'name' => 'big cat',
        'age'  => 27 // sex 有默认值 不传
    ]
];

try {
    // 依赖的单例注册
    IOCContainer::singleton(Foo::class, new Foo());

    // 依赖的契约注册 StorageEngine 相当于契约者 注册关联具体的实现类
    // IOCContainer::bind(StorageEngine::class, FileStorageEngine::class);
    IOCContainer::bind(StorageEngine::class, RedisStorageEngine::class);
    
    // 运行
    $result = IOCContainer::run($route['controller'], $route['action'], $route['params']);
    
    echo $result;
} catch (Exception $e) {
    echo $e->getMessage();
}
