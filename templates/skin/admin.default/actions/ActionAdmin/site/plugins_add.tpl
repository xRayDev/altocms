{extends file='_index.tpl'}

{block name="content-body"}
    <div class="span12">
        <div class="btn-group">
            <a href="{router page='admin'}plugins/add/" class="btn btn-primary active tip-top"
               title="{$aLang.action.admin.plugin_load}"><i class="icon-plus-sign"></i></a>
        </div>

        <div class="btn-group">
            <a class="btn {if $sMode=='all' || $sMode==''}active{/if}" href="{router page='admin'}plugins/list/all/">
                {$aLang.action.admin.all_plugins}
            </a>
            <a class="btn {if $sMode=='active'}active{/if}" href="{router page='admin'}plugins/list/active/">
                {$aLang.action.admin.active_plugins}
            </a>
            <a class="btn {if $sMode=='inactive'}active{/if}" href="{router page='admin'}plugins/list/inactive/">
                {$aLang.action.admin.inactive_plugins}
            </a>
        </div>

        <form action="" method="post" id="form_plugins_add" enctype="multipart/form-data" class="uniform">
            <input type="hidden" name="security_ls_key" value="{$ALTO_SECURITY_KEY}"/>

            <div class="b-wbox">
                <div class="b-wbox-content">
                    <input type="file" id="plugin_arc" name="plugin_arc"/>
                </div>
            </div>

            <div class="navbar navbar-inner">
                <button type="submit" name="submit_plugins_del" class="btn btn-primary pull-right">
                    {$aLang.action.admin.plugin_load}
                </button>
            </div>
        </form>
    </div>
{/block}
