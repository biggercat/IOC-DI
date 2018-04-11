# IOC-DI
一套使用PHP实现的基于DI模式的IOC容器
借助 PHP 反射机制实现的一套 依赖自动解析注入 的 IOC/DI 容器，可以方便作为 Web MVC 框架 的应用容器。

## 运作模式
1、依赖的自动注入：你只需要在需要的位置注入你需要的依赖即可，运行时容器会自动解析依赖（存在子依赖也可以自动解析）将对应的实例注入到你需要的位置。

2、依赖的单例注入：某些情况下我们需要保持依赖的全局单例特性，比如 Web 框架中的 Request 依赖，我们需要将整个请求响应周期中的所有注入 Request 依赖的位置同步为在路由阶段解析完请求体的 Request 实例，这样我们在任何位置都可以访问全局的请求体对象。

3、依赖的契约注入：比如我们依赖某 Storage，目前使用 FileStorage 来实现，后期发现性能瓶颈，要改用 RedisStorage 来实现，如果代码中大量使用 FileStorage 作为依赖注入，这时候就需要花费精力去改代码了。我们可以使用接口 Storage 作为契约，将具体的实现类 FileStorage / RedisStorage 通过容器的绑定机制关联到 Storage 上，依赖注入 Storage，后期切换存储引擎只需要修改绑定即可。

4、标量参数关联传值：依赖是自动解析注入的，剩余的标量参数则可以通过关联传值，这样比较灵活，没必要把默认值的参数放在函数参数最尾部。这点我还是蛮喜欢 python 的函数传值风格的。

## 代码示例

### 服务类
```PHP
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

// 契约注入 顶层契约者
interface StorageEngine
{
    public function info();
}

// 契约实现 契约服务
class FileStorageEngine implements StorageEngine
{
    public $msg = "file storage engine!" . PHP_EOL;

    public function info()
    {
        $this->msg =  "file storage engine!" . PHP_EOL;
    }
}

// 契约实现 契约服务
class RedisStorageEngine implements StorageEngine
{
    public $msg = "redis storage engine!" . PHP_EOL;

    public function info()
    {
        $this->msg =  "redis storage engine!" . PHP_EOL;
    }
}
```

### 运行类

```PHP
// 具体的运行类 比如某个控制器
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
```

### 路由结构

```PHP
// 路由信息很 MVC 吧
$route = [
    'controller' => BigCatController::class, // 运行的类
    'action'     => 'index', // 运行的方法
    'params'     => [ // 运行的参数
        'name' => 'big cat',
        'age'  => 27 // sex 有默认值 不传
    ]
];
```

### 运行

```PHP
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
```

### 运行结果

foo hello, modified by index method!
foo hello, modified by index method!

bar hello, modified by index method!
bar nothing to say!

redis storage engine!

name big cat, age 27, sex male
