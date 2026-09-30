<style>
    .menu-info-trigger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        color: #6c757d;
        cursor: help;
        transition: color 0.2s;
    }

    .menu-info-trigger:hover {
        color: #0d6efd;
    }

    #menuInfoTooltip {
        position: fixed;
        z-index: 2147483647;
        display: block;
        box-sizing: border-box;
        width: 280px;
        max-width: calc(100vw - 20px);
        padding: 10px 12px;
        background-color: #1f2937;
        color: #ffffff;
        border-radius: 8px;
        box-shadow: 0 5px 18px rgba(0, 0, 0, 0.25);
        font-size: 12px;
        line-height: 1.8;
        text-align: right;
        direction: rtl;
        pointer-events: none;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-50%);
        will-change: left, top, opacity;
        transition: opacity 0.15s ease, visibility 0.15s ease;
    }

    #menuInfoTooltip.show {
        opacity: 1;
        visibility: visible;
    }

    #menuInfoTooltip::after {
        content: "";
        position: absolute;
        top: 50%;
        border: 7px solid transparent;
        transform: translateY(-50%);
    }

    #menuInfoTooltip.place-left::after {
        left: 100%;
        border-left-color: #1f2937;
    }

    #menuInfoTooltip.place-right::after {
        right: 100%;
        border-right-color: #1f2937;
    }
</style>

<script>
    (function () {
        if (window.adminInfoTooltipInitialized) return;
        window.adminInfoTooltipInitialized = true;

        var tooltip = document.getElementById('menuInfoTooltip');
        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.id = 'menuInfoTooltip';
            tooltip.setAttribute('role', 'tooltip');
            document.body.appendChild(tooltip);
        }
        var activeTrigger = null;

        function closestTrigger(target) {
            while (target && target !== document) {
                if (target.nodeType === 1 && target.classList.contains('menu-info-trigger') && target.hasAttribute('data-tooltip')) return target;
                target = target.parentNode;
            }
            return null;
        }
        function showTooltip(trigger) {
            var message = trigger.getAttribute('data-tooltip');
            if (!message) return;
            activeTrigger = trigger;
            tooltip.textContent = message;
            tooltip.classList.remove('place-left', 'place-right');
            tooltip.classList.add('show');
            var triggerRect = trigger.getBoundingClientRect();
            var tooltipRect = tooltip.getBoundingClientRect();
            var margin = 10;
            var left = triggerRect.left - tooltipRect.width - margin;
            var placement = 'place-left';
            if (left < margin) { left = triggerRect.right + margin; placement = 'place-right'; }
            left = Math.max(margin, Math.min(left, window.innerWidth - tooltipRect.width - margin));
            var halfHeight = tooltipRect.height / 2;
            var top = triggerRect.top + (triggerRect.height / 2);
            top = Math.max(halfHeight + margin, Math.min(top, window.innerHeight - halfHeight - margin));
            tooltip.style.left = left + 'px';
            tooltip.style.top = top + 'px';
            tooltip.classList.add(placement);
        }
        function hideTooltip(trigger) {
            if (trigger && activeTrigger && trigger !== activeTrigger) return;
            activeTrigger = null;
            tooltip.classList.remove('show', 'place-left', 'place-right');
        }
        document.addEventListener('pointerover', function (event) {
            var trigger = closestTrigger(event.target);
            if (trigger && (!event.relatedTarget || !trigger.contains(event.relatedTarget))) showTooltip(trigger);
        });
        document.addEventListener('pointerout', function (event) {
            var trigger = closestTrigger(event.target);
            if (trigger && (!event.relatedTarget || !trigger.contains(event.relatedTarget))) hideTooltip(trigger);
        });
        document.addEventListener('focusin', function (event) { var trigger = closestTrigger(event.target); if (trigger) showTooltip(trigger); });
        document.addEventListener('focusout', function (event) { var trigger = closestTrigger(event.target); if (trigger) hideTooltip(trigger); });
        window.addEventListener('scroll', function () { hideTooltip(); }, true);
        window.addEventListener('resize', function () { hideTooltip(); });
    })();
</script>
