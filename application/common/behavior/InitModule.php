<?php
// +----------------------------------------------------------------------
// | OpenCMF [ Simple Efficient Excellent ]
// +----------------------------------------------------------------------
// | Copyright (c) 2014 http://www.opencmf.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: jry <598821125@qq.com>
// +----------------------------------------------------------------------
namespace app\common\behavior;

defined('THINK_PATH') or exit();

/**
 * 初始化允许访问模块信息
 * @author jry <598821125@qq.com>
 */
class InitModule
{
    /**
     * 行为扩展的执行入口必须是run
     * @author jry <598821125@qq.com>
     */
    public function run(&$content)
    {
        // 安装模式下直接返回
        if (defined('BIND_MODULE') && BIND_MODULE === 'install') {
            return;
        }

        // 通过hook方法注入动态方法
        \think\Request::hook('isWeixin', 'is_weixin');
        \think\Request::hook('hostname', 'hostname');

        // 获取配置
        $config = config();

        // 数据缓存前缀
        $config['data_cache_prefix'] = strtolower(ENV_PRE . MODULE_MARK . '_');

        // 获取数据库存储的配置
        $database_config = model('Admin/Config')->lists();

        // 兼容TP3配置
        $config['app_trace'] = $database_config['SHOW_PAGE_TRACE'];

        // 允许访问模块列表加上安装的功能模块
        $module_name_list = model('Admin/Module')
            ->where(array('status' => 1, 'is_system' => 0))
            ->getField('name', true);
        $module_allow_list = array_merge(
            config('module_allow_list'),
            $module_name_list
        );
        if (MODULE_MARK === 'Admin') {
            $module_allow_list[] = 'admin';
        }
        config('module_allow_list', $module_allow_list);

        // 如果是后台访问自动设置默认模块为Admin
        if (MODULE_MARK === 'Admin') {
            config('default_module', 'admin');
        }

        // 系统主页地址配置
        $config['top_home_domain'] = request()->domain();
        if (isset($config['app_sub_domain_deploy']) && $config['app_sub_domain_deploy']) {
            $host = explode('.', request()->hostname());
            if (count($host) > 2) {
                $config['top_home_domain'] = request()->scheme() . '://www' . strstr(request()->hostname(), '.');

                // 设置cookie和session的作用域
                $config['cookie_domain']             = strstr(request()->hostname(), '.');
                $config['session_options']           = C('session_options');
                $config['session_options']['domain'] = $config['COOKIE_DOMAIN'];
            }
        }
        $config['home_domain']   = request()->domain();
        $config['home_page']     = $config['home_domain'] . __ROOT__;
        $config['top_home_page'] = $config['top_home_domain'] . __ROOT__;

        // 模块初始化
        $request = \think\Request::instance();
        if ($config['app_multi_module']) {
            // 多模块部署
            $dispatch = \think\App::routeCheck($request, $config);
            $result   = $dispatch['module'];
            if (is_string($result)) {
                $result = explode('/', $result);
            }
            $module    = strip_tags(strtolower($result[0] ?: $config['default_module']));
            $bind      = \think\Route::getBind('module');
            $available = false;
            if ($bind) {
                // 绑定模块
                list($bindModule) = explode('/', $bind);
                if (empty($result[0])) {
                    $module    = $bindModule;
                    $available = true;
                } elseif ($module == $bindModule) {
                    $available = true;
                }
            } elseif (!in_array($module, $config['deny_module_list']) && is_dir(APP_PATH . $module)) {
                $available = true;
            }

            // 模块初始化
            if ($module && $available) {
                // 初始化模块
                $request->module($module);
                // 模块请求缓存检查
                $request->cache($config['request_cache'], $config['request_cache_expire']);
            }
        } else {
            // 单一模块部署
            $module = '';
            $request->module($module);
        }

        config($config);
    }
}
