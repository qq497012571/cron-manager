<?php
namespace SuperCronManager;

/**
 * crontab格式解析工具类
 * @author jlb <497012571@qq.com>
 */
class CronParser
{
    private static $_format = ['i','G','j','n'];

    /**
     * 检查crontab格式是否支持
     * @param  string $cronstr 
     * @return boolean true|false
     */
    public static function check($cronstr) 
    {
    	$cronstr = trim($cronstr);

    	if (count(preg_split('#\s+#', $cronstr)) !== 4) {
    		return false;
    	}

        $reg = '#^(\*(/\d+)?|\d+([,\d\-]+)?)\s+(\*(/\d+)?|\d+([,\d\-]+)?)\s+(\*(/\d+)?|\d+([,\d\-]+)?)\s+(\*(/\d+)?|\d+([,\d\-]+)?)$#';
        if (!preg_match($reg, $cronstr)) {
            return false;
        }

        return true;
    }

    /**
     * 格式化crontab格式字符串[只支持 分钟,小时,日期,月份] 四种格式.
     * @param  string $cronstr
     * @param  interge $size 设置返回符合条件的时间数量, 默认为1
     * @return array 返回符合格式的时间
     */
	public static function formatToDate($cronstr, $size = 1) 
	{

		if (!static::check($cronstr)) {
			throw new Exception("格式错误", 1);
		}

		$tags = preg_split('#\s+#', $cronstr);

		$crons = array();
		$crons[] = static::parseTag($tags[0], 0, 59); // 分钟
		$crons[] = static::parseTag($tags[1], 0, 23); // 小时
		$crons[] = static::parseTag($tags[2], 1, 31); // 日期
		$crons[] = static::parseTag($tags[3], 1, 12); // 月份
		
		$parseCrons = array_filter($crons);
	
		$list = [];
        $format = implode('-', static::$_format);
        $nowtime = date('Y-m-d H:i:s');
        
        // 月份循环深度
        $monMax = 12;

        // 如果分钟集合太大,会严重影响效率,故牺牲月份详细度.
        if (count($parseCrons[0]) > 10) {
        	$monMax = 1;
        }

        foreach ($parseCrons[3] as $m => &$mon) {
            foreach ($parseCrons[2] as $day) {
                foreach ($parseCrons[1] as $hour) {
                    foreach ($parseCrons[0] as $min) {
                    	$date = implode('-', [str_pad($min, 2, '0', STR_PAD_LEFT ), $hour, $day, $mon]);
            			if ($vars = get_object_vars(date_create_from_format($format, $date))) {
            				// 过滤小于当前时间的数据
            				if ($vars['date'] < $nowtime) {
				                continue;
				            }
				            $list[] = substr($vars['date'], 0,19);
            			}
                    }
                }
            }
            if (!$monMax) {
            	break;
            }
            $monMax--;
        }

        unset($parseCrons);
        sort($list);

		return array_slice(array_filter($list), 0, $size);
	}
	/**
	 * 解析元素
	 * @param  string $tag  元素标签
	 * @param  integer $tmin 最小值
	 * @param  integer $tmax 最大值
     * @throws Exception
	 */
	protected static function parseTag($tag, $tmin, $tmax)
	{
		if ($tag == '*') {
			return range($tmin, $tmax);
		}

		$step = 1;
        $dateList = [];

		if (false !== strpos($tag, '/')) {
			$tmp = explode('/', $tag);
			$step = isset($tmp[1]) ? $tmp[1] : 1;
			
            $dateList = range($tmin, $tmax, $step);
		}
		else if (false !== strpos($tag, '-')) {
			list($min, $max) = explode('-', $tag);
			if ($min > $max) {
				list($min, $max) = [$max, $min];
			}
            $dateList = range($min, $max, $step);
		}
		else if (false !== strpos($tag, ',')) {
            $dateList = explode(',', $tag);
		}
		else {
			$dateList = array($tag);
		}

        // 越界判断
        foreach ($dateList as $num) {
            if ($num < $tmin || $num > $tmax) {
                throw new Exception('数值越界');
            }
        }

        sort($dateList);

		return $dateList;
	}

}

// date_default_timezone_set('PRC');

// print_r(CronParser::formatToDate('*/2 * * *', 200));