<?php
/**
 * Created by PhpStorm.
 * User: liupan
 * Date: 2019/3/24
 * Time: 下午12:38
 */

namespace app\admin\service;

use think\Db;
use think\Exception;

class FileService
{
    protected $type = 'Local';
    protected $table = 'file';
    protected $driver;
    public function __construct()
    {
        $className = "app\admin\service\\file\driver\\{$this->type}";
        $this->driver = new $className();
    }

    public function upload($file, $type, $uuid = null)
    {
        $config = config('upload.file_type');
        if(!isset($config[$type]))
            throw new Exception("上传类型{$type}未进行配置");
        $config = $config[$type];
        $originInfo = $file->getInfo();
        $name = $originInfo['name'];
        //检测文件类型
        $array = explode('.', $originInfo['name']);
        $ext = $array[count($array)-1];
        if(!isset($config['ext']) || !in_array($ext, $config['ext']))
            throw new Exception("文件类型{$ext}不允许上传");
        //检测文件大小
        $size = $originInfo['size'];
        if($size > $config['size'])
            throw new Exception('文件大小超出限制');
        //检测文件数量
        if($uuid !== null)
        {
            $count = Db::name($this->table)->where('type', $type)->where('uuid', $uuid)->count();
            if(isset($config['num']) && $count >= $config['num'])
                throw new Exception('该位置已达到最大文件数');
        }
        //上传
        list($path, $url) = $this->driver->upload($file, $type, $uuid);
        $path = str_replace("\\",'/', $path);
        //入库
        if($uuid !== null)
        {
            $rzt = Db::name($this->table)->insert([
                'type' => $type,
                'uuid' => $uuid,
                'driver' => $this->type,
                'path' => $path,
                'name' => $name,
                'ext' => $ext,
                'size' => $size,
                'url' => $url,
                'create_time' => time()
            ]);
            if(!$rzt)
                throw new Exception('文件信息入库失败');
        }
        return $path;
    }




    public function delete($id, $uuid = null)
    {
        if($uuid === null)
        {
            $fileInfo = Db::name($this->table)->get($id);
        }else{
            $fileInfo = Db::name($this->table)->where('uuid', $uuid)->get($id);
        }
        if(empty($fileInfo))
            throw new Exception("文件不存在");
        $this->driver->delete($fileInfo);
        $rzt = Db::name($this->table)->where('id', $fileInfo['id'])->delete();
        if(!$rzt)
            throw new Exception("删除数据库记录失败");
    }

}