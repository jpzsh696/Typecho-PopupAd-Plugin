<?php
/**
 * 弹窗广告插件 - 专业美观、高性能
 * 
 * @package PopupAd 
 * @author zhzyk.vip
 * @version 2.0
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
            _t('小图URL'),
            _t('小图广告地址（支持PNG/GIF透明图片），留空则不显示'));
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
        
        // 小图位置设置
        $position = new Typecho_Widget_Helper_Form_Element_Radio('position', 
            array(
                'bottom-right' => _t('右下角'),
                'bottom-left' => _t('左下角'),
                'top-right' => _t('右上角'),
                'top-left' => _t('左上角')
            ), 
            'bottom-right', 
            _t('小图位置'), 
            _t('选择小图在页面上的显示位置')
        );
        $form->addInput($position);
        
        // 小图拖动设置
        $draggable = new Typecho_Widget_Helper_Form_Element_Radio('draggable', 
            array(
                '1' => _t('启用'),
                '0' => _t('禁用')
            ), 
            '1', 
            _t('小图可拖动'),
            _t('允许用户拖动小图到合适位置')
        );
        $form->addInput($draggable);
        
        // 滚动时隐藏小图
        $hideOnScroll = new Typecho_Widget_Helper_Form_Element_Radio('hideOnScroll', 
            array(
                '1' => _t('启用'),
                '0' => _t('禁用')
            ), 
            '1', 
            _t('滚动时隐藏小图'),
            _t('当用户滚动页面时自动隐藏小图')
        );
        $form->addInput($hideOnScroll);
        
        // 大图延迟显示
        $bigDelay = new Typecho_Widget_Helper_Form_Element_Text('bigDelay', null, '1000',
            _t('大图延迟显示（毫秒）'),
            _t('大图弹窗显示的延迟时间（毫秒），默认1000毫秒'));
        $form->addInput($bigDelay->addRule('isInteger', _t('请输入整数')));
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
        $position = isset($options->position) ? $options->position : 'bottom-right';
        $draggable = isset($options->draggable) ? $options->draggable : '1';
        $hideOnScroll = isset($options->hideOnScroll) ? $options->hideOnScroll : '1';
        $bigDelay = isset($options->bigDelay) ? intval($options->bigDelay) : 1000;

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
            echo self::generateSmallImageHtml($smallImage, $targetUrl, $position, $draggable);
        }

        // 输出大图HTML结构（如果设置了大图且在有效期内）
        if (!empty($bigImage) && $isValid) {
            echo self::generateBigImageHtml($bigImage, $targetUrl);
        }
        
        // 输出CSS样式
        echo self::generatePopupCss($position);
        
        // 输出JS逻辑
        if ((!empty($bigImage) || !empty($smallImage)) && $isValid) {
            echo self::generatePopupJs($intervalHours, $draggable, $hideOnScroll, $position, $bigDelay);
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
                <img src="{$imageUrl}" alt="广告大图" class="popup-ad-image" loading="lazy">
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
     * @param string $position 小图位置
     * @param string $draggable 是否可拖动
     * @return string HTML代码
     */
    private static function generateSmallImageHtml($imageUrl, $targetUrl, $position, $draggable)
    {
        $draggableClass = $draggable == '1' ? 'popup-ad-draggable' : '';
        
        return <<<HTML
<div id="popup-ad-small" class="popup-ad-corner {$draggableClass}" data-position="{$position}">
    <a href="{$targetUrl}" target="_blank" class="popup-ad-link">
        <img src="{$imageUrl}" alt="广告小图" class="popup-ad-corner-image" loading="lazy">
    </a>
    <span class="popup-ad-close-corner" title="关闭">&times;</span>
</div>
HTML;
    }
    
    /**
     * 生成弹窗CSS样式
     * 
     * @param string $position 小图位置
     * @return string CSS代码
     */
    private static function generatePopupCss($position)
    {
        // 根据位置设置初始样式
        $positionStyle = '';
        switch ($position) {
            case 'bottom-left':
                $positionStyle = 'bottom: 25px; left: 25px;';
                break;
            case 'top-right':
                $positionStyle = 'top: 25px; right: 25px;';
                break;
            case 'top-left':
                $positionStyle = 'top: 25px; left: 25px;';
                break;
            default: // bottom-right
                $positionStyle = 'bottom: 25px; right: 25px;';
        }
        
        return <<<CSS
<style>
/* 大图弹窗样式 */
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
    background: rgba(0,0,0,0.7);
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
    border: none;
    box-shadow: none;
    transition: transform 0.3s ease;
}

.popup-ad-image:hover {
    transform: scale(1.02);
    cursor: pointer;
}

/* 关闭按钮 */
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

/* 小图弹窗样式 */
.popup-ad-corner {
    position: fixed;
    z-index: 999998;
    transition: all 0.3s ease;
    display: block;
    {$positionStyle}
    animation: popupSlideIn 0.5s ease-out;
    will-change: transform, opacity; /* 优化性能 */
}

@keyframes popupSlideIn {
    from { opacity: 0; transform: translateY(50px); }
    to { opacity: 1; transform: translateY(0); }
}

.popup-ad-corner:hover {
    transform: scale(1.05);
}

.popup-ad-corner-image {
    max-height: 160px;
    max-width: 90vw;
    border: none;
    box-shadow: none;
    filter: drop-shadow(0 5px 15px rgba(0,0,0,0.3));
    transition: all 0.3s;
    display: block;
}

.popup-ad-corner-image:hover {
    transform: scale(1.05);
    cursor: pointer;
}

/* 小关闭按钮 */
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

/* 可拖动样式 */
.popup-ad-draggable {
    cursor: move;
    user-select: none;
}

/* 响应式设计 */
@media (max-width: 768px) {
    .popup-ad-corner {
        bottom: 15px !important;
        right: 15px !important;
        left: auto !important;
        top: auto !important;
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

/* 滚动时隐藏样式 */
.popup-ad-hide-on-scroll {
    transform: translateY(100px) !important;
    opacity: 0 !important;
    transition: all 0.5s ease !important;
    pointer-events: none;
}
</style>
CSS;
    }
    
    /**
     * 生成高性能JS逻辑
     * 
     * @param int $intervalHours 大图弹窗间隔小时数
     * @param string $draggable 小图是否可拖动
     * @param string $hideOnScroll 滚动时是否隐藏小图
     * @param string $position 小图位置
     * @param int $bigDelay 大图延迟显示时间
     * @return string JS代码
     */
    private static function generatePopupJs($intervalHours, $draggable, $hideOnScroll, $position, $bigDelay)
    {
        $intervalSeconds = $intervalHours * 3600;
        $draggable = $draggable === '1' ? 'true' : 'false';
        $hideOnScroll = $hideOnScroll === '1' ? 'true' : 'false';
        
        return <<<JS
<script>
// 使用DOMContentLoaded确保DOM就绪
document.addEventListener('DOMContentLoaded', function() {
    // 函数节流优化
    function throttle(func, limit) {
        let lastFunc;
        let lastRan;
        return function() {
            const context = this;
            const args = arguments;
            if (!lastRan) {
                func.apply(context, args);
                lastRan = Date.now();
            } else {
                clearTimeout(lastFunc);
                lastFunc = setTimeout(function() {
                    if ((Date.now() - lastRan) >= limit) {
                        func.apply(context, args);
                        lastRan = Date.now();
                    }
                }, limit - (Date.now() - lastRan));
            }
        };
    }
    
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
            
            // 使用requestAnimationFrame优化动画
            requestAnimationFrame(function() {
                container.style.opacity = '1';
            });
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
            setTimeout(function() {
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
            setTimeout(function() {
                container.style.display = 'none';
            }, 300);
        }
    }

    // 事件委托处理所有关闭按钮
    document.body.addEventListener('click', function(e) {
        // 大图关闭按钮
        if (e.target.classList.contains('popup-ad-close')) {
            closeBigPopup();
            e.stopPropagation();
        }
        
        // 小图关闭按钮
        if (e.target.classList.contains('popup-ad-close-corner')) {
            closeSmallPopup();
            e.stopPropagation();
        }
        
        // 点击背景关闭大图弹窗
        if (e.target.classList.contains('popup-ad-main')) {
            closeBigPopup();
        }
    });

    // 检查并显示大图弹窗（延迟显示）
    if (shouldShowBigPopup() && document.getElementById('popup-ad-big')) {
        setTimeout(showBigPopup, {$bigDelay});
    }
    
    // 小图功能
    const smallAd = document.getElementById('popup-ad-small');
    if (smallAd) {
        // 确保小图可见
        smallAd.style.display = 'block';
        smallAd.style.opacity = '1';
        
        // 小图拖动功能
        if ({$draggable}) {
            let isDragging = false;
            let currentX, currentY, initialX, initialY;
            let xOffset = 0, yOffset = 0;
            
            smallAd.addEventListener("mousedown", dragStart);
            smallAd.addEventListener("touchstart", dragStart, { passive: false });
            
            function dragStart(e) {
                // 忽略关闭按钮和链接的点击
                if (e.target.classList.contains('popup-ad-close-corner') || 
                    e.target.classList.contains('popup-ad-link')) {
                    return;
                }
                
                if (e.type === "touchstart") {
                    e.preventDefault(); // 防止触摸时滚动
                    initialX = e.touches[0].clientX - xOffset;
                    initialY = e.touches[0].clientY - yOffset;
                } else {
                    initialX = e.clientX - xOffset;
                    initialY = e.clientY - yOffset;
                }
                
                isDragging = true;
                
                // 添加事件监听器
                document.addEventListener("mousemove", drag);
                document.addEventListener("touchmove", drag, { passive: false });
                document.addEventListener("mouseup", dragEnd);
                document.addEventListener("touchend", dragEnd);
            }
            
            function drag(e) {
                if (!isDragging) return;
                
                if (e.cancelable) e.preventDefault();
                
                if (e.type === "touchmove") {
                    currentX = e.touches[0].clientX - initialX;
                    currentY = e.touches[0].clientY - initialY;
                } else {
                    currentX = e.clientX - initialX;
                    currentY = e.clientY - initialY;
                }
                
                xOffset = currentX;
                yOffset = currentY;
                
                // 使用transform优化性能
                smallAd.style.transform = "translate(" + currentX + "px, " + currentY + "px)";
            }
            
            function dragEnd() {
                isDragging = false;
                
                // 移除事件监听器
                document.removeEventListener("mousemove", drag);
                document.removeEventListener("touchmove", drag);
                document.removeEventListener("mouseup", dragEnd);
                document.removeEventListener("touchend", dragEnd);
                
                // 保存位置到本地存储
                localStorage.setItem('popup_ad_small_position', JSON.stringify({
                    x: currentX,
                    y: currentY
                }));
            }
            
            // 加载保存的位置
            const savedPosition = localStorage.getItem('popup_ad_small_position');
            if (savedPosition) {
                try {
                    const pos = JSON.parse(savedPosition);
                    smallAd.style.transform = "translate(" + pos.x + "px, " + pos.y + "px)";
                    xOffset = pos.x;
                    yOffset = pos.y;
                } catch (e) {
                    console.error('Error loading saved position:', e);
                }
            }
        }
        
        // 滚动时隐藏小图（使用节流优化）
        if ({$hideOnScroll}) {
            let lastScrollTop = 0;
            const scrollHideClass = 'popup-ad-hide-on-scroll';
            
            const handleScroll = throttle(function() {
                const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                
                // 向下滚动时隐藏
                if (scrollTop > lastScrollTop && scrollTop > 100) {
                    smallAd.classList.add(scrollHideClass);
                } 
                // 向上滚动时显示
                else if (scrollTop < lastScrollTop) {
                    smallAd.classList.remove(scrollHideClass);
                }
                
                lastScrollTop = scrollTop;
            }, 100);
            
            window.addEventListener('scroll', handleScroll);
        }
    }
    
    // 智能避让页面底部元素
    const adjustForFooter = function() {
        const smallAd = document.getElementById('popup-ad-small');
        if (!smallAd) return;
        
        const footer = document.querySelector('footer, .footer, #footer');
        if (footer) {
            const footerRect = footer.getBoundingClientRect();
            const adRect = smallAd.getBoundingClientRect();
            
            if (adRect.bottom > footerRect.top - 20) {
                smallAd.style.bottom = (window.innerHeight - footerRect.top + 20) + 'px';
            }
        }
    };
    
    // 初始调整和窗口大小变化时调整
    window.addEventListener('load', adjustForFooter);
    window.addEventListener('resize', throttle(adjustForFooter, 200));
});
</script>
JS;
    }
}
