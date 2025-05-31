<?php
/**
 * 弹窗广告插件
 * 
 * @package PopupAd 
 * @author zhzyk.vip
 * @version 1.4
 * @link https://zhzyk.vip
 */
class PopupAd_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Archive')->footer = array('PopupAd_Plugin', 'render');
        return _t('弹窗广告插件已激活，请前往设置配置参数');
    }

    /**
     * 禁用插件
     */
    public static function deactivate()
    {
        return _t('弹窗广告插件已禁用');
    }

    /**
     * 插件配置面板
     * 
     * @param Typecho_Widget_Helper_Form $form
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        // 大图设置
        $bigImage = new Typecho_Widget_Helper_Form_Element_Text('bigImage', null, null,
            _t('大图URL（居中显示）'),
            _t('居中显示的弹窗大图地址（支持PNG/GIF透明图片），留空则不显示'));
        $form->addInput($bigImage);

        // 小图设置
        $smallImage = new Typecho_Widget_Helper_Form_Element_Text('smallImage', null, null,
            _t('小图URL（靠右显示）'),
            _t('靠右下角显示的小图地址（支持PNG/GIF透明图片），留空则不显示'));
        $form->addInput($smallImage);

        // 跳转链接
        $targetUrl = new Typecho_Widget_Helper_Form_Element_Text('targetUrl', null, '#',
            _t('跳转链接地址'),
            _t('点击图片后跳转的URL'));
        $form->addInput($targetUrl->addRule('url', _t('请输入有效的URL地址')));

        // 有效期设置
        $expiryDate = new Typecho_Widget_Helper_Form_Element_Text('expiryDate', null, null,
            _t('有效期至'),
            _t('格式：YYYY-MM-DD HH:ii:ss，留空表示永久有效'));
        $form->addInput($expiryDate);

        // 大图弹窗间隔设置
        $intervalHours = new Typecho_Widget_Helper_Form_Element_Text('intervalHours', null, '24',
            _t('大图弹窗间隔（小时）'),
            _t('同一用户两次大图弹窗的最小间隔时间（小时），设为0表示每次访问都显示'));
        $form->addInput($intervalHours->addRule('isInteger', _t('请输入整数')));
    }

    /**
     * 个人用户配置面板（不需要）
     * 
     * @param Typecho_Widget_Helper_Form $form
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}
    
    /**
     * 插件实现方法 - 前端输出
     */
    public static function render()
    {
        $options = Typecho_Widget::widget('Widget_Options')->plugin('PopupAd');
        $bigImage = $options->bigImage;
        $smallImage = $options->smallImage;
        $targetUrl = $options->targetUrl;
        $expiryDate = $options->expiryDate;
        $intervalHours = $options->intervalHours ? intval($options->intervalHours) : 24;

        // 检查有效期
        $isValid = true;
        if (!empty($expiryDate)) {
            $currentTime = time();
            $expiryTime = strtotime($expiryDate);
            if ($expiryTime && $currentTime > $expiryTime) {
                $isValid = false;
            }
        }

        // 输出小图HTML结构（如果设置了小图且在有效期内）
        if (!empty($smallImage) && $isValid) {
            echo self::generateSmallImageHtml($smallImage, $targetUrl);
        }

        // 输出大图HTML结构（如果设置了大图且在有效期内）
        if (!empty($bigImage) && $isValid) {
            echo self::generateBigImageHtml($bigImage, $targetUrl);
        }
        
        // 输出CSS样式
        echo self::generatePopupCss();
        
        // 输出JS逻辑
        if ((!empty($bigImage) || !empty($smallImage)) && $isValid) {
            echo self::generatePopupJs($intervalHours);
        }
    }
    
    /**
     * 生成大图弹窗HTML结构
     * 
     * @param string $imageUrl 大图URL
     * @param string $targetUrl 跳转链接
     * @return string HTML代码
     */
    private static function generateBigImageHtml($imageUrl, $targetUrl)
    {
        return <<<HTML
<div id="popup-ad-big" style="display:none; opacity:0;">
    <div class="popup-ad-main">
        <div class="popup-ad-content">
            <a href="{$targetUrl}" target="_blank" class="popup-ad-link">
                <img src="{$imageUrl}" alt="广告大图" class="popup-ad-image">
            </a>
            <span class="popup-ad-close" title="关闭">&times;</span>
        </div>
    </div>
</div>
HTML;
    }
    
    /**
     * 生成小图HTML结构
     * 
     * @param string $imageUrl 小图URL
     * @param string $targetUrl 跳转链接
     * @return string HTML代码
     */
    private static function generateSmallImageHtml($imageUrl, $targetUrl)
    {
        return <<<HTML
<div id="popup-ad-small">
    <div class="popup-ad-corner">
        <a href="{$targetUrl}" target="_blank" class="popup-ad-link">
            <img src="{$imageUrl}" alt="广告小图" class="popup-ad-corner-image">
        </a>
        <span class="popup-ad-close-corner" title="关闭">&times;</span>
    </div>
</div>
HTML;
    }
    
    /**
     * 生成弹窗CSS样式（优化透明图片支持）
     * 
     * @return string CSS代码
     */
    private static function generatePopupCss()
    {
        return <<<CSS
<style>
/* 大图弹窗样式 - 支持透明图片 */
.popup-ad-main {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 999999;
    background: rgba(0,0,0,0.7); /* 半透明背景 */
    animation: fadeIn 0.3s ease-out;
    transition: opacity 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.popup-ad-content {
    position: relative;
    max-width: 90%;
    max-height: 90%;
    animation: popupScale 0.4s cubic-bezier(0.22, 0.61, 0.36, 1);
    transition: all 0.3s ease;
}

@keyframes popupScale {
    from { transform: scale(0.95); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

.popup-ad-image {
    max-width: 100%;
    max-height: 90vh;
    display: block;
    /* 移除边框和阴影，支持透明效果 */
    border: none;
    box-shadow: none;
    /* 添加平滑过渡 */
    transition: transform 0.3s ease;
}

.popup-ad-image:hover {
    transform: scale(1.02);
    cursor: pointer;
}

/* 透明友好的关闭按钮 */
.popup-ad-close {
    position: absolute;
    top: 15px;
    right: 15px;
    color: #fff;
    font-size: 28px;
    cursor: pointer;
    background: rgba(0,0,0,0.4);
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    transition: all 0.3s;
    z-index: 10;
    text-shadow: 0 1px 3px rgba(0,0,0,0.3);
}

.popup-ad-close:hover {
    background: rgba(255,0,0,0.8);
    transform: rotate(90deg);
}

/* 小图弹窗样式 - 优化透明效果 */
.popup-ad-corner {
    position: fixed;
    bottom: 25px;
    right: 25px;
    z-index: 999998;
    animation: slideIn 0.5s cubic-bezier(0.18, 0.89, 0.32, 1.28);
    transition: all 0.3s ease;
}

@keyframes slideIn {
    from { transform: translateX(100px) translateY(100px); opacity: 0; }
    to { transform: translateX(0) translateY(0); opacity: 1; }
}

.popup-ad-corner:hover {
    transform: translateY(-5px);
}

.popup-ad-corner-image {
    max-height: 160px;
    max-width: 90vw;
    /* 移除边框和阴影，支持透明效果 */
    border: none;
    box-shadow: none;
    /* 添加发光效果，使透明图片更醒目 */
    filter: drop-shadow(0 5px 15px rgba(0,0,0,0.3));
    transition: all 0.3s;
}

.popup-ad-corner-image:hover {
    transform: scale(1.05);
    cursor: pointer;
}

/* 透明友好的小关闭按钮 */
.popup-ad-close-corner {
    position: absolute;
    top: -8px;
    right: -8px;
    color: #fff;
    background: rgba(0,0,0,0.5);
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s;
    z-index: 10;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}

.popup-ad-close-corner:hover {
    background: rgba(255,0,0,0.8);
    transform: rotate(90deg) scale(1.1);
}

/* 响应式设计 */
@media (max-width: 768px) {
    .popup-ad-corner {
        bottom: 15px;
        right: 15px;
    }
    
    .popup-ad-corner-image {
        max-height: 130px;
    }
    
    .popup-ad-close {
        top: 10px;
        right: 10px;
        width: 32px;
        height: 32px;
        font-size: 24px;
    }
}

@media (max-width: 480px) {
    .popup-ad-corner {
        bottom: 10px;
        right: 10px;
    }
    
    .popup-ad-corner-image {
        max-height: 100px;
    }
    
    .popup-ad-close-corner {
        top: -6px;
        right: -6px;
        width: 20px;
        height: 20px;
        font-size: 14px;
    }
}
</style>
CSS;
    }
    
    /**
     * 生成弹窗JS逻辑（修复关闭问题）
     * 
     * @param int $intervalHours 大图弹窗间隔小时数
     * @return string JS代码
     */
    private static function generatePopupJs($intervalHours)
    {
        $intervalSeconds = $intervalHours * 3600;
        return <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 检查是否应该显示大图弹窗
    function shouldShowBigPopup() {
        // 间隔为0表示每次都显示
        if ({$intervalHours} === 0) return true;
        
        // 检查本地存储
        const lastPopup = localStorage.getItem('popup_ad_big_last_show');
        if (!lastPopup) return true;
        
        const currentTime = Math.floor(Date.now() / 1000);
        return (currentTime - parseInt(lastPopup)) >= {$intervalSeconds};
    }

    // 显示大图弹窗
    function showBigPopup() {
        const container = document.getElementById('popup-ad-big');
        if (container) {
            container.style.display = 'block';
            
            // 添加淡入效果
            setTimeout(() => {
                container.style.opacity = '1';
            }, 10);
        }
        
        // 更新最后显示时间
        localStorage.setItem('popup_ad_big_last_show', Math.floor(Date.now() / 1000));
    }

    // 关闭大图弹窗
    function closeBigPopup() {
        const container = document.getElementById('popup-ad-big');
        if (container) {
            container.style.opacity = '0';
            container.style.transition = 'opacity 0.3s ease';
            setTimeout(() => {
                container.style.display = 'none';
            }, 300);
        }
    }

    // 关闭小图弹窗
    function closeSmallPopup() {
        const container = document.getElementById('popup-ad-small');
        if (container) {
            container.style.opacity = '0';
            container.style.transition = 'all 0.3s ease';
            container.style.transform = 'translateY(20px)';
            setTimeout(() => {
                container.style.display = 'none';
            }, 300);
        }
    }

    // 绑定关闭按钮事件
    document.addEventListener('click', function(e) {
        // 大图关闭按钮
        if (e.target.closest('.popup-ad-close')) {
            closeBigPopup();
            e.stopPropagation();
        }
        
        // 小图关闭按钮
        if (e.target.closest('.popup-ad-close-corner')) {
            closeSmallPopup();
            e.stopPropagation();
        }
    });

    // 点击背景关闭大图弹窗
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('popup-ad-main')) {
            closeBigPopup();
        }
    });

    // 检查并显示大图弹窗（延迟1秒显示）
    if (shouldShowBigPopup() && document.getElementById('popup-ad-big')) {
        setTimeout(showBigPopup, 1000);
    }
});
</script>
JS;
    }
}