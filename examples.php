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

// 契约注入
interface StorageEngine
{
    public function info();
}

// 契约实现
class FileStorageEngine implements StorageEngine
{
    public $msg = "file storage engine!" . PHP_EOL;

    public function info()
    {
        $this->msg =  "file storage engine!" . PHP_EOL;
    }
}

// 契约实现
class RedisStorageEngine implements StorageEngine
{
    public $msg = "redis storage engine!" . PHP_EOL;

    public function info()
    {
        $this->msg =  "redis storage engine!" . PHP_EOL;
    }
}
