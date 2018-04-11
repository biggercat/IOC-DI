<?php
/*----------------------------------------------------------------------------------------------------
 | @author big cat
 |----------------------------------------------------------------------------------------------------
 | IOC 容器
 | 1、自动解析依赖     自动的对依赖进行解析，实例化，注入
 |                     /------------------------------------------------------------------------------
 |                     | 比如你用 Redis 或 File 做引擎存储 Session，可以定义一个顶层契约接口 Storage
 | 2、契约注入---------| 将具体的实现类 RedisStorage or FileStorage 的实例绑定到此契约
 |                     | 依赖此契约进行注入 后期可以灵活的更换或者扩展新的存储引擎
 |                     \-------------------------------------------------------------------------------
 | 3、单例注入         可以将依赖绑定为单例，实现此依赖的同步
 | 4、关联参数传值     标量参数采用关联传值，可设定默认值
 | 备注：关联传参才舒服, ($foo = 'foo', $bar), 跳过 foo 直接给 bar 传值多舒服
 |-----------------------------------------------------------------------------------------------------
 | public static methods:
 |   singleton // 单例服务绑定
 |   bind      // 服务绑定
 |   run       // 运行容器
 | private static methods:
 |   getParam    // 获取依赖参数
 |   getInstance // 获取依赖实例
 |-----------------------------------------------------------------------------------------------------
 */

class IOCContainer
{
    /**
     * 注册到容器内的依赖--服务
     * 可以通过 singleton($alias, $instance) 绑定全局单例依赖
     * 可以通过 bind($alias, $class_name) 绑定顶层契约依赖
     * 容器解析依赖时会优先检查是否为注册的内部依赖 如不是则加载外部依赖类实例化后注入
     * @var array
     */
    public static $dependencyServices = array();

    /**
     * 单例模式服务注册
     * 将具体的实例绑定到服务 整个生命周期中此服务的各处依赖注入都用此实例
     * @param  [type] $service  绑定的服务别名
     * @param  [type] $provider 服务提供者：具体的实例或可实例的类
     * @return [type]                   [description]
     */
    public static function singleton($service, $provider)
    {
        static::bind($service, $provider, true);
    }

    /**
     * 服务注册
     * 注册依赖服务到容器内 容器将优先使用此类服务 可以实现契约注入
     * 契约注入：A Interface 可以作为 B Class 和 C Class 的代理人（契约者）注入 B Class 或 C Class 的实例
     * 具体看你绑定的谁 可以灵活切换底层具体的实现代码
     * @param  [type]  $service    [description]
     * @param  [type]  $provider   [description]
     * @param  boolean $singleton     [description]
     * @return [type]                 [description]
     */
    public static function bind($service, $provider, $singleton = false)
    {
        // 单例绑定服务提供者必须为服务的实例 以便全局单例绑定
        if ($singleton && ! is_object($provider)) {
            throw new Exception("service provider must be an instance of $provider!", 4041);
        }
    
        // 若非单例则校验服务提供者是否存在
        if (! $singleton && ! class_exists($provider)) {
            throw new Exception("service provider not exists!", 4042);
        }
    
        // singleton 标识服务是否为单例模式
        // 单例场景则 provider 为具体的实例 否则为某提供者类
        static::$dependencyServices[$service] = [
            'provider'  => $provider,
            'singleton' => $singleton,
        ];
    }

    /**
     * 获取类实例
     * 通过反射获取构造参数
     * 返回对应的类实例
     * @param  [type] $class_name [description]
     * @return [type]             [description]
     */
    private static function getInstance($class_name)
    {
        //方法参数分为 params 和 default_values
        //如果一个开放构造类作为依赖注入传入它类，我们应该将此类注册为全局单例服务
        $params = static::getParams($class_name);
        return (new ReflectionClass($class_name))->newInstanceArgs($params['params']);
    }

    /**
     * 反射方法参数类型
     * 对象参数：构造对应的实例 同时检查是否为单例模式的实例
     * 标量参数：返回参数名 索引路由参数取值
     * 默认值参数：检查路由参数中是否存在本参数 无则取默认值
     * @param  [type] $class_name [description]
     * @param  string $method     [description]
     * @return [type]             [description]
     */
    private static function getParams($class_name, $method = '__construct')
    {
        $params_set['params'] = array();
        $params_set['default_values'] = array();

        //反射检测类是否显示声明或继承父类的构造方法
        //若无则说明构造参数为空
        if ($method == '__construct') {
            $classRf = new ReflectionClass($class_name);
            if (! $classRf->hasMethod('__construct')) {
                return $params_set;
            }
        }

        //反射方法 获取参数
        $methodRf = new ReflectionMethod($class_name, $method);
        $params = $methodRf->getParameters();

        if (! empty($params)) {
            foreach ($params as $key => $param) {
                if ($paramClass = $param->getClass()) {// 对象参数 获取对象实例
                    $param_class_name = $paramClass->getName();
                    if (array_key_exists($param_class_name, static::$dependencyServices)) {// 是否为注册的服务
                        if (static::$dependencyServices[$param_class_name]['singleton']) {// 单例模式直接返回已注册的实例
                            $params_set['params'][] = static::$dependencyServices[$param_class_name]['provider'];
                        } else {// 非单例则返回提供者的新的实例
                            $params_set['params'][] = static::getInstance(static::$dependencyServices[$param_class_name]['provider']);
                        }
                    } else {// 没有做绑定注册的类
                        $params_set['params'][] = static::getInstance($param_class_name);
                    }
                } else {// 标量参数 获取变量名作为路由映射 包含默认值的记录默认值
                    $param_name = $param->getName();

                    if ($param->isDefaultValueAvailable()) {// 是否包含默认值
                        $param_default_value = $param->getDefaultValue();
                        $params_set['default_values'][$param_name] = $param_default_value;
                    }

                    $params_set['params'][] = $param_name;
                }
            }
        }

        return $params_set;
    }

    /**
     * 容器的运行入口 主要负责加载类方法，并将运行所需的标量参数做映射和默认值处理
     * @param  [type] $class_name 运行类
     * @param  [type] $method     运行方法
     * @param  array  $params     运行参数
     * @return [type]             输出
     */
    public static function run($class_name, $method, array $params = array())
    {
        if (! class_exists($class_name)) {
            throw new Exception($class_name . "not found!", 4040);
        }

        if (! method_exists($class_name, $method)) {
            throw new Exception($class_name . "::" . $method . " not found!", 4041);
        }

        // 获取要运行的类
        $classInstance = static::getInstance($class_name);
        // 获取要运行的方法的参数
        $method_params = static::getParams($class_name, $method);
        
        // 关联传入的运行参数
        $method_params = array_map(function ($param) use ($params, $method_params) {
            if (is_object($param)) {// 对象参数 以完成依赖解析的具体实例
                return $param;
            }

            // 以下为关联传值 可通过参数名映射的方式关联传值 可省略含有默认值的参数
            if (array_key_exists($param, $params)) {// 映射传递路由参数
                return $params[$param];
            }

            if (array_key_exists($param, $method_params['default_values'])) {// 默认值
                return $method_params['default_values'][$param];
            }

            throw new Exception($param . ' is necessary parameters', 4042); // 路由中没有的则包含默认值
        }, $method_params['params']);

        // 运行
        return call_user_func_array([$classInstance, $method], $method_params);
    }
}
