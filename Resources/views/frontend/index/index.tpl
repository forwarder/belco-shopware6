{extends file="parent:frontend/index/index.tpl"}

{block name="frontend_index_header_javascript"}
    {$smarty.block.parent}

    {if $belcoConfig.shopId}
        <script>window.belcoConfig = {$belcoConfig};</script>
    {/if}

    {literal}<script>(function(e,t){var n=e.createElement(t);n.async=true;n.src="https://cdn.belco.io/widget.min.js";n.onload=n.onreadystatechange=function(){var e=this.readyState;if(e)if(e!="complete")if(e!="loaded")return;try{Belco("init", belcoConfig)}catch(t){}};var r=e.getElementsByTagName(t)[0];r.parentNode.insertBefore(n,r)})(document,"script")</script>{/literal}
{/block}