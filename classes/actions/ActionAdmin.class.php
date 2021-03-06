<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS
 * @Project URI: http://altocms.com
 * @Description: Advanced Community Engine
 * @Version: 0.9a
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

/**
 * @package actions
 * @since 0.9
 */
class ActionAdmin extends Action {
    /**
     * Текущий пользователь
     *
     * @var ModuleUser_EntityUser|null
     */
    protected $oUserCurrent = null;
    /**
     * Главное меню
     *
     * @var string
     */
    protected $sMenuItem = '';

    /**
     * Инициализация
     *
     * @return string
     */
    public function Init() {
        if ($this->User_IsAuthorization()) {
            $this->oUserCurrent = $this->User_GetUserCurrent();
        }

        /**
         * Если нет прав доступа - перекидываем на 404 страницу
         * Но нужно это делать через Router::Location, т.к. Viewer может быть уже инициирован
         */
        if (!$this->oUserCurrent || !$this->oUserCurrent->isAdministrator()) {
            //return Router::Action('error', '404');
            return Router::Location('error/404/');
        }
        $this->SetDefaultEvent('dashboard');
    }

    /**
     * Регистрация евентов
     */
    protected function RegisterEvent() {
        $this->AddEvent('dashboard', 'EventDashboard');
        $this->AddEvent('report', 'EventReport');
        $this->AddEvent('phpinfo', 'EventPhpinfo');

        $this->AddEvent('pages', 'EventPages');
        $this->AddEvent('blogs', 'EventBlogs');
        $this->AddEvent('topics', 'EventTopics');
        $this->AddEvent('comments', 'EventComments');

        $this->AddEvent('users', 'EventUsers');
        $this->AddEvent('banlist', 'EventBanlist');
        $this->AddEvent('invites', 'EventInvites');

        $this->AddEvent('config', 'EventConfig');
        $this->AddEvent('userfields', 'EventUserfields');

        $this->AddEvent('skins', 'EventSkins');
        $this->AddEvent('widgets', 'EventWidgets');
        $this->AddEvent('plugins', 'EventPlugins');

        $this->AddEvent('logs', 'EventLogs');

        $this->AddEvent('reset', 'EventReset');
        $this->AddEvent('commentstree', 'EventCommentsTree');
        $this->AddEvent('recalcfavourites', 'EventRecalculateFavourites');
        $this->AddEvent('recalcvotes', 'EventRecalculateVotes');
        $this->AddEvent('recalctopics', 'EventRecalculateTopics');
        $this->AddEvent('checkdb', 'EventCheckDb');

        //поля контента
        $this->AddEvent('content', 'EventContent');
        $this->AddEvent('contentadd', 'EventContentAdd');
        $this->AddEvent('contentedit', 'EventContentEdit');

        $this->AddEvent('fieldadd', 'EventAddField');
        $this->AddEvent('fieldedit', 'EventEditField');
        $this->AddEvent('fielddelete', 'EventDeleteField');
        $this->AddEvent('ajaxchangeordertypes', 'EventAjaxChangeOrderTypes');
        $this->AddEvent('ajaxchangeorderfields', 'EventAjaxChangeOrderFields');
    }

    protected function _getMode($nParam = 0, $sDefault, $aAvail = null) {
        $sKey = Router::GetAction() . '.' . Router::GetActionEvent() . '.' . $nParam;
        $sMode = $this->GetParam($nParam, $this->Session_Get($sKey, $sDefault));
        if (!is_null($aAvail) && !is_array($aAvail)) $aAvail = array($aAvail);
        if (is_null($aAvail) || ($sMode && in_array($sMode, $aAvail))) {
            $this->_saveMode(0, $sMode);
        }
        return $sMode;
    }

    protected function _saveMode($nParam = 0, $sData) {
        $sKey = Router::GetAction() . '.' . Router::GetActionEvent() . '.' . $nParam;
        $this->Session_Set($sKey, $sData);
    }

    /**********************************************************************************
     ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
     **********************************************************************************
     */

    public function EventDashboard() {
        $this->_setTitle($this->Lang_Get('action.admin.menu_info_dashboard'));
        $this->SetTemplateAction('info/index');

        $aData = array('e-alto' => ALTO_VERSION, 'e-uniq' => $this->Security_GetUniqKey());
        $aPlugins = $this->Plugin_GetPluginsList(true);
        foreach ($aPlugins as $oPlugin) {
            $aData['p-' . $oPlugin->GetId()] = $oPlugin->GetVersion();
        }

        $this->Viewer_Assign('sUpdatesRequest', base64_encode(http_build_query($aData)));
        $this->Viewer_Assign('sUpdatesRefresh', true);
    }

    public function EventReport() {
        $this->_setTitle($this->Lang_Get('action.admin.menu_info'));
        $this->SetTemplateAction('info/report');

        if ($sReportMode = getRequest('report', null, 'post')) {
            $this->_EventReportOut($this->_getInfoData(), $sReportMode);
        }

        $this->Viewer_Assign('aInfoData', $this->_getInfoData());
    }

    protected function _getInfoData() {
        $aPlugins = $this->Plugin_GetList();
        $aActivePlugins = $this->Plugin_GetActivePlugins();
        $aPluginList = array();
        foreach ($aActivePlugins as $sPlugin) {
            if (isset($aPlugins[$sPlugin])) {
                $aPliginProps = $aPlugins[$sPlugin];
                $sPluginName = htmlspecialchars((string)$aPliginProps['property']->name->data);
                $aPluginInfo = array(
                    'item' => $sPlugin,
                    'label' => $sPluginName,
                );
                if ($aPliginProps['property']->version) {
                    $aPluginInfo['value'] = 'v.' . htmlspecialchars((string)$aPliginProps['property']->version);
                }
                $sPluginClass = 'Plugin' . ucfirst($sPlugin);
                if (class_exists($sPluginClass) && method_exists($sPluginClass, 'GetUpdateInfo')) {
                    $oPlugin = new $sPluginClass;
                    $aPluginInfo['.html'] = ' - ' . $oPlugin->GetUpdateInfo();
                }
                $aPluginList[$sPlugin] = $aPluginInfo;
            }
        }

        $aSiteStat = $this->Admin_GetSiteStat();
        $sSmartyVersion = $this->Viewer_GetSmartyVersion();

        $aInfo = array(
            'versions' => array(
                'label' => $this->Lang_Get('action.admin.info_versions'),
                'data' => array(
                    'php' => array('label' => $this->Lang_Get('action.admin.info_version_php'), 'value' => PHP_VERSION,),
                    'smarty' => array('label' => $this->Lang_Get('action.admin.info_version_smarty'), 'value' => $sSmartyVersion ? $sSmartyVersion : 'n/a',),
                    'alto' => array('label' => $this->Lang_Get('action.admin.info_version_alto'), 'value' => ALTO_VERSION,),
                )

            ),
            'site' => array(
                'label' => $this->Lang_Get('action.admin.site_info'),
                'data' => array(
                    'url' => array('label' => $this->Lang_Get('action.admin.info_site_url'), 'value' => Config::Get('path.root.url'),),
                    'skin' => array('label' => $this->Lang_Get('action.admin.info_site_skin'), 'value' => Config::Get('view.skin', Config::DEFAULT_CONFIG_INSTANCE),),
                    //'jslib' => array('label' => $this->Lang_Get('action.admin.info_site_jslib'), 'value' => Config::Get('js.lib'),),
                    'client' => array('label' => $this->Lang_Get('action.admin.info_site_client'), 'value' => $_SERVER['HTTP_USER_AGENT'],),
                ),
            ),
            'plugins' => array(
                'label' => $this->Lang_Get('action.admin.active_plugins'),
                'data' => $aPluginList,
            ),
            'stats' => array(
                'label' => $this->Lang_Get('action.admin.site_statistics'),
                'data' => array(
                    'users' => array('label' => $this->Lang_Get('action.admin.site_stat_users'), 'value' => $aSiteStat['users'],),
                    'blogs' => array('label' => $this->Lang_Get('action.admin.site_stat_blogs'), 'value' => $aSiteStat['blogs'],),
                    'topics' => array('label' => $this->Lang_Get('action.admin.site_stat_topics'), 'value' => $aSiteStat['topics'],),
                    'comments' => array('label' => $this->Lang_Get('action.admin.site_stat_comments'), 'value' => $aSiteStat['comments'],),
                ),
            ),
        );

        return $aInfo;
    }

    protected function _EventReportOut($aInfo, $sMode = 'txt') {
        $this->Security_ValidateSendForm();
        $sMode = strtolower($sMode);
        $aParams = array(
            'filename' => $sFileName = str_replace(array('.', '/'), '_', str_replace(array('http://', 'https://'), '', Config::Get('path.root.web'))) . '.' . $sMode,
            'date' => date('Y-m-d H:i:s'),
        );

        if ($sMode == 'xml') {
            $this->_reportXml($aInfo, $aParams);
        } else {
            $this->_reportTxt($aInfo, $aParams);
        }
        exit;
    }

    protected function _reportTxt($aInfo, $aParams) {
        $sText = '[report]' . "\n";
        foreach ($aParams as $sKey => $sVal) {
            $sText .= $sKey . ' = ' . $sVal . "\n";
        }
        $sText .= "\n";

        foreach ($aInfo as $sSectionKey => $aSection) {
            if (getRequest('adm_report_' . $sSectionKey)) {
                $sText .= '[' . $sSectionKey . '] ; ' . $aSection['label'] . "\n";
                foreach ($aSection['data'] as $sItemKey => $aItem) {
                    $sText .= $sItemKey . ' = ' . $aItem['value'] . '; ' . $aItem['label'] . "\n";
                }
                $sText .= "\n";
            }
        }
        $sText .= "; EOF\n";

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $aParams['filename'] . '"');
        echo $sText;
        exit;
    }

    protected function _reportXml($aInfo, $aParams) {
        $sText = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<report';
        foreach ($aParams as $sKey => $sVal) {
            $sText .= ' ' . $sKey . '="' . $sVal . '"';
        }
        $sText .= ">\n";
        foreach ($aInfo as $sSectionKey => $aSection) {
            if (getRequest('adm_report_' . $sSectionKey)) {
                $nLevel = 1;
                $sText .= str_repeat(' ', $nLevel * 2) . '<' . $sSectionKey . ' label="' . $aSection['label'] . '">' . "\n";
                $nLevel += 1;
                foreach ($aSection['data'] as $sItemKey => $aItem) {
                    $sText .= str_repeat(' ', $nLevel * 2) . '<' . $sItemKey . ' label="' . $aItem['label'] . '">';
                    if (is_array($aItem['value'])) {

                        $sText .= "\n" . str_repeat(' ', $nLevel * 2) . '</' . $sItemKey . '>' . "\n";
                    } else {
                        $sText .= $aItem['value'];
                    }
                    $sText .= '</' . $sItemKey . '>' . "\n";
                }
                $nLevel -= 1;
                $sText .= str_repeat(' ', $nLevel * 2) . '</' . $sSectionKey . '>' . "\n";
            }
        }

        $sText .= '</report>';

        header('Content-Type: text/xml; charset=utf-8', true);
        header('Content-Disposition: attachment; filename="' . $aParams['filename'] . '"', true);
        echo $sText;
        exit;
    }

    public function EventPhpinfo() {
        $this->_setTitle($this->Lang_Get('action.admin.menu_info_php'));
        $this->SetTemplateAction('info/phpinfo');

        $this->_phpInfo(1);
    }

    protected function _phpInfo($nMode = 0) {
        if ($nMode) {
            ob_start();
            phpinfo(-1);

            $sPhpinfo = preg_replace(
                array('#^.*<body>(.*)</body>.*$#ms', '#<h2>PHP License</h2>.*$#ms',
                    '#<h1>Configuration</h1>#', "#\r?\n#", "#</(h1|h2|h3|tr)>#", '# +<#',
                    "#[ \t]+#", '#&nbsp;#', '#  +#', '# class=".*?"#', '%&#039;%',
                    '#<tr>(?:.*?)" src="(?:.*?)=(.*?)" alt="PHP Logo" /></a>'
                        . '<h1>PHP Version (.*?)</h1>(?:\n+?)</td></tr>#',
                    '#<h1><a href="(?:.*?)\?=(.*?)">PHP Credits</a></h1>#',
                    '#<tr>(?:.*?)" src="(?:.*?)=(.*?)"(?:.*?)Zend Engine (.*?),(?:.*?)</tr>#',
                    "# +#", '#<tr>#', '#</tr>#'),
                array('$1', '', '', '', '</$1>' . "\n", '<', ' ', ' ', ' ', '', ' ',
                    '<h2>PHP Configuration</h2>' . "\n" . '<tr><td>PHP Version</td><td>$2</td></tr>' .
                        "\n" . '<tr><td>PHP Egg</td><td>$1</td></tr>',
                    '<tr><td>PHP Credits Egg</td><td>$1</td></tr>',
                    '<tr><td>Zend Engine</td><td>$2</td></tr>' . "\n" .
                        '<tr><td>Zend Egg</td><td>$1</td></tr>', ' ', '%S%', '%E%'),
                ob_get_clean());
            $aSections = explode('<h2>', strip_tags($sPhpinfo, '<h2><th><td>'));
            unset($aSections[0]);

            $aPhpInfo = array();
            foreach ($aSections as $sSection) {
                $n = substr($sSection, 0, strpos($sSection, '</h2>'));
                preg_match_all(
                    '#%S%(?:<td>(.*?)</td>)?(?:<td>(.*?)</td>)?(?:<td>(.*?)</td>)?%E%#',
                    $sSection, $aMatches, PREG_SET_ORDER);
                foreach ($aMatches as $m) {
                    if (!isset($m[2])) $m[2] = '';
                    $aPhpInfo[$n][$m[1]] = (!isset($m[3]) || $m[2] == $m[3]) ? $m[2] : array_slice($m, 2);
                }
            }
            $this->Viewer_Assign('aPhpInfo', array('collection' => $aPhpInfo, 'count' => sizeof($aPhpInfo)));
        } else {
            ob_start();
            phpinfo();
            $phpinfo = ob_get_contents();
            ob_end_clean();
            $phpinfo = str_replace("\n", ' ', $phpinfo);
            $info = '';
            if (preg_match('|<style\s*[\w="/]*>(.*)<\/style>|imu', $phpinfo, $match)) $info .= $match[0];
            if (preg_match('|<body\s*[\w="/]*>(.*)<\/body>|imu', $phpinfo, $match)) $info .= $match[1];
            if (!$info) $info = $phpinfo;
            $this->Viewer_Assign('sPhpInfo', $info);
        }
    }

    /**********************************************************************************/

    /**
     * Site settings
     */
    public function EventConfig() {
        $this->_setTitle($this->Lang_Get('action.admin.config_title'));

        $sMode = $this->_getMode(0, 'base');

        if ($sMode == 'links') {
            $this->_eventConfigLinks();
        } elseif ($sMode == 'edit') {
            $this->_eventConfigEdit($sMode);
        } else {
            $this->_eventConfigFields($sMode);
        }
        $this->Viewer_Assign('sMode', $sMode);
    }

    /**
     * Site settings > Config parameters
     *
     * @param   string  $sSelectedSection
     */
    protected function _eventConfigFields($sSelectedSection) {
        $this->SetTemplateAction('settings/config_fields');

        $aFields = F::IncludeFile(Config::Get('path.config.dir') . 'actions/admin.settings.php', false, true);
        foreach ($aFields as $nSec => $aSection) {
            foreach ($aSection as $nKey => $aItem) {
                $aItem['text'] = $this->Lang_Get($aItem['label']);
                if (isset($aItem['help'])) $aItem['help'] = $this->Lang_Get($aItem['help']);
                if (isset($aItem['config'])) {
                    $aItem['value'] = Config::Get($aItem['config']);
                    $aItem['config'] = str_replace('.', '--', $aItem['config']);
                    if (!isset($aItem['valtype']) && isset($aItem['type']) && $aItem['type'] == 'checkbox') {
                        $aItem['valtype'] = 'boolean';
                    }
                }
                if (isset($aItem['type']) && $aItem['type'] == 'password') {
                    $aItem['valtype'] = 'string';
                }
                $aFields[$nSec][$nKey] = $aItem;
            }
        }
        if (($aData = $this->GetPost()) && isset($aFields[$sSelectedSection])) {
            $this->_eventConfigSave($aFields[$sSelectedSection], $aData);
        }
        if (!isset($aFields[$sSelectedSection])) {
            $sSelectedSection = F::Array_FirstKey($aFields);
            $this->_saveMode(0, $sSelectedSection);
        }
        $this->Viewer_Assign('aFields', $aFields[$sSelectedSection]);
    }

    /**
     * Site settings > Links
     */
    protected function _eventConfigLinks() {
        if ($sHomePage = $this->GetPost('submit_data_save')) {
            $aConfig = array();
            if ($sHomePage = $this->GetPost('homepage')) {
                if ($sHomePage == 'page') {
                    $sHomePage = 'page/' . $this->GetPost('page_url');
                }
                $aConfig = array(
                    'router.config.action_default' => 'homepage',
                    'router.config.homepage' => $sHomePage,
                );
            }
            if ($sDraftLink = $this->GetPost('draft_link')) {
                if ($sDraftLink == 'on') {
                    $aConfig['module.topic.draft_link'] = true;
                } else {
                    $aConfig['module.topic.draft_link'] = false;
                }
            }
            Config::WriteCustomConfig($aConfig);
            Router::Location('admin/config/links/');
            exit;
        }
        $this->SetTemplateAction('settings/config_links');
        $sHomePage = Config::Get('router.config.homepage');
        $sHomePageUrl = '';
        if (!$sHomePage || $sHomePage == 'index') {
            $sHomePageSelect = 'index';
            $sHomePageUrl = '';
        } elseif (strpos($sHomePage, 'page/') === 0) {
            list ($sHomePageSelect, $sHomePageUrl) = explode('/', $sHomePage, 2);
        } else {
            $sHomePageSelect = $sHomePage;
        }
        $aPages = $this->Page_GetPages();
        $this->Viewer_Assign('sHomePageSelect', $sHomePageSelect);
        $this->Viewer_Assign('sHomePageUrl', $sHomePageUrl);
        $this->Viewer_Assign('aPages', $aPages);
    }

    /**
     * Site settings > Edit
     */
    protected function _eventConfigEdit() {
        $aUnits = array(
            'S' => array('name' => 'seconds'),
            'M' => array('name' => 'minutes'),
            'H' => array('name' => 'hours'),
            'D' => array('name' => 'days'),
        );

        if ($this->GetPost('submit_data_save')) {
            $aConfig = array();
            if ($this->GetPost('view--tinymce')) {
                $aConfig['view.tinymce'] = true;
            } else {
                $aConfig['view.tinymce'] = false;
            }
            if ($this->GetPost('view--noindex')) {
                $aConfig['view.noindex'] = true;
            } else {
                $aConfig['view.noindex'] = false;
            }
            if ($this->GetPost('tag_required')) {
                $aConfig['module.topic.allow_empty_tags'] = false;
            } else {
                $aConfig['module.topic.allow_empty_tags'] = true;
            }
            if ($nVal = intval($this->GetPost('module--topic--max_length'))) {
                $aConfig['module.topic.max_length'] = $nVal;
            }
            $aConfig['module.comment.edit.enable'] = false;
            if ($this->GetPost('edit_comment') == 'on') {
                $nEditTime = intval($this->GetPost('edit_comment_time'));
                if ($nEditTime) {
                    $sEditUnit = '';
                    if ($this->GetPost('edit_comment_unit')) {
                        foreach ($aUnits as $sKey => $sUnit) {
                            if ($sUnit['name'] == $this->GetPost('edit_comment_unit')) {
                                $sEditUnit = $sKey;
                                break;
                            }
                        }
                    }
                    if (!$sEditUnit) $sEditUnit = 'S';
                    if ($sEditUnit == 'D') $nEditTime = F::ToSeconds('P' . $nEditTime . 'D');
                    else $nEditTime = F::ToSeconds('PT' . $nEditTime . $sEditUnit);
                    $aConfig['module.comment.edit.enable'] = $nEditTime;
                }
            }

            Config::WriteCustomConfig($aConfig);
            Router::ReturnBack(true);
            exit;
        }
        $this->SetTemplateAction('settings/config_edit');
        $nCommentEditTime = F::ToSeconds(Config::Get('module.comment.edit.enable'));
        if ($nCommentEditTime) {
            $sCommentEditUnit = $aUnits['S']['name'];
            if (($nCommentEditTime % 60) == 0) {
                $nCommentEditTime = $nCommentEditTime / 60;
                $sCommentEditUnit = $aUnits['M']['name'];
                if (($nCommentEditTime % 60) == 0) {
                    $nCommentEditTime = $nCommentEditTime / 60;
                    $sCommentEditUnit = $aUnits['H']['name'];
                    if (($nCommentEditTime % 24) == 0) {
                        $nCommentEditTime = $nCommentEditTime / 24;
                        $sCommentEditUnit = $aUnits['D']['name'];
                    }
                }
            }
        } else {
            $sCommentEditUnit = $aUnits['S']['name'];
        }
        $this->Viewer_Assign('nCommentEditTime', $nCommentEditTime);
        $this->Viewer_Assign('sCommentEditUnit', $sCommentEditUnit);
        $this->Viewer_Assign('aTimeUnits', $aUnits);
    }

    /**
     * Сохраняет пользовательские настройки
     *
     * @param $aFields
     * @param $aData
     */
    protected function _eventConfigSave($aFields, $aData) {
        $aConfig = array();
        foreach ($aFields as $aParam) {
            if (isset($aParam['config'])) {
                if (isset($aData[$aParam['config']])) {
                    $sVal = $aData[$aParam['config']];
                } else {
                    $sVal = '';
                }
                if (($sVal === '') && isset($aParam['default'])) {
                    $sVal = $aParam['default'];
                }
                if (isset($aParam['valtype'])) {
                    settype($sVal, $aParam['valtype']);
                }
                $aConfig[str_replace('--', '.', $aParam['config'])] = $sVal;
            }
        }
        if ($aConfig) {
            Config::WriteCustomConfig($aConfig);
        }
        Router::ReturnBack(true);
    }

    /**********************************************************************************/

    public function EventWidgets() {
        $this->_setTitle($this->Lang_Get('action.admin.widgets_title'));
        $this->SetTemplateAction('site/widgets');

        $sMode = $this->GetParam(0);
        $aWidgets = $this->Widget_GetWidgets(true);

        if ($sMode == 'edit') {
            $sWidgetId = $this->GetParam(1);
            if (isset($aWidgets[$sWidgetId])) {
                $this->_eventWidgetsEdit($aWidgets[$sWidgetId]);
            }
        } elseif (($sCmd = $this->GetPost('widget_action')) && ($aWidgets = $this->GetPost('widget_sel'))) {
            $aWidgets = array_keys($aWidgets);
            if ($sCmd == 'activate') {
                $this->_eventWidgetsActivate($aWidgets);
            } elseif ($sCmd == 'deactivate') {
                $this->_eventWidgetsDeactivate($aWidgets);
            }
        }
        $this->Viewer_Assign('aWidgetsList', $aWidgets);
    }

    public function _eventWidgetsEdit($oWidget) {
        if ($this->GetPost()) {
            $aConfig = array();
            $sPrefix = 'widget.' . $oWidget->GetId() . '.config.';
            if ($xVal = $this->GetPost('widget_group')) {
                $aConfig[$sPrefix . 'group'] = $xVal;
            }

            $aConfig[$sPrefix . 'active'] = (bool)$this->GetPost('widget_active');

            $xVal = strtolower($this->GetPost('widget_priority'));
            $aConfig[$sPrefix . 'priority'] = ($xVal == 'top' ? 'top' : intval($xVal));

            if ($this->GetPost('widget_display') == 'period') {
                if ($sFrom = $this->GetPost('widget_period_from')) {
                    $aConfig[$sPrefix . 'display.date_from'] = date('Y-m-d', strtotime($sFrom));;
                }
                if ($sUpto = $this->GetPost('widget_period_upto')) {
                    $aConfig[$sPrefix . 'display.date_upto'] = date('Y-m-d', strtotime($sUpto));;
                }
            }

            $xVal = strtolower($this->GetPost('widget_visitors'));
            $aConfig[$sPrefix . 'visitors'] = (in_array($xVal, array('users', 'admins')) ? $xVal : null);

            Config::WriteCustomConfig($aConfig);
            Router::Location('admin/widgets');
        }
        $this->_setTitle($this->Lang_Get('action.admin.widget_edit_title'));
        $this->SetTemplateAction('site/widgets_add');
        $this->Viewer_Assign('oWidget', $oWidget);
    }

    public function _eventWidgetsActivate($aWidgets) {
        if ($this->GetPost()) {
            $aConfig = array();
            foreach ($aWidgets as $sWidgetId) {
                $sPrefix = 'widget.' . $sWidgetId . '.config.';
                $aConfig[$sPrefix . 'active'] = true;
            }
            Config::WriteCustomConfig($aConfig);
            Router::Location('admin/widgets');
        }
    }

    public function _eventWidgetsDeactivate($aWidgets) {
        if ($this->GetPost()) {
            $aConfig = array();
            foreach ($aWidgets as $sWidgetId) {
                $sPrefix = 'widget.' . $sWidgetId . '.config.';
                $aConfig[$sPrefix . 'active'] = false;
            }
            Config::WriteCustomConfig($aConfig);
            Router::Location('admin/widgets');
        }
    }

    /**********************************************************************************/

    public function EventPlugins() {
        $this->_setTitle($this->Lang_Get('action.admin.plugins_title'));
        $this->SetTemplateAction('site/plugins');

        if ($this->GetParam(0) == 'add') {
            return $this->_EventPluginsAdd();
        } elseif ($this->GetParam(0) == 'config') {
            $this->sMenuSubItemSelect = 'config';
            $this->PluginDelBlock('right', 'AdminInfo');
            return $this->_eventPluginsConfig();
        } else {
            $sParam = $this->GetParam(0, 'list');
            if ($sParam != 'list') {
                $aPlugins = $this->Plugin_GetActivePlugins();
                if (in_array($sParam, $aPlugins)) {
                    return $this->_EventPluginsExternalAdmin(0);
                }
            }
            return $this->_eventPluginsList();
        }
    }

    protected function _eventPluginsConfig() {
        $this->PluginDelBlock('right', 'AdminInfo');
        $sPluginCode = $this->getParam(1);
        $oPlugin = $this->PluginAceadminpanel_Plugin_GetPlugin($sPluginCode);
        if ($oPlugin) {
            $sClass = $oPlugin->GetAdminClass();
            return $this->EventPluginsExec($sClass);
        } else {
            return false;
        }
    }

    protected function _EventPluginsMenu() {
        $this->PluginDelBlock('right', 'AdminInfo');
        $sEvent = Router::GetActionEvent();
        if (isset($this->aExternalEvents[$sEvent])) {
            return $this->EventPluginsExec($this->aExternalEvents[$sEvent]);
        }
    }

    protected function _eventPluginsList() {
        if ($this->GetPost('plugin_action') == 'delete' && ($aSelectedPlugins = $this->GetPost('plugin_sel'))) {
            // Удаление плагинов
            $this->_eventPluginsDelete($aSelectedPlugins);
        } elseif ($sAction = $this->GetPost('plugin_action')) {
            $aPlugins = $this->GetPost('plugin_sel');
            if ($sAction == 'activate') {
                $this->_eventPluginsActivate($aPlugins);
            } elseif ($sAction == 'deactivate') {
                $this->_eventPluginsDectivate($aPlugins);
            }
            Router::Location('admin/plugins/');
        }

        $sMode = $this->GetParam(1, 'all');

        if ($sMode == 'active') {
            $aPlugins = $this->Plugin_GetPluginsList(true);
        } elseif ($sMode == 'inactive') {
            $aPlugins = $this->Plugin_GetPluginsList(false);
        } else {
            $aPlugins = $this->Plugin_GetPluginsList();
        }

        $this->Viewer_Assign('aPluginList', $aPlugins);
        $this->Viewer_Assign('sMode', $sMode);
    }

    protected function _eventPluginsActivate($aPlugins) {
        if (is_array($aPlugins)) {
            // если передан массив, то обрабатываем только первый элемент
            $sPluginId = array_shift($aPlugins);
        } else {
            $sPluginId = (string)$aPlugins;
        }
        return $this->Plugin_Activate($sPluginId);
    }

    protected function _eventPluginsDectivate($aPlugins) {
        if (is_array($aPlugins)) {
            // если передан массив, то обрабатываем только первый элемент
            $sPluginId = array_shift($aPlugins);
        } else {
            $sPluginId = (string)$aPlugins;
        }
        return $this->Plugin_Deactivate($sPluginId);
    }

    protected function _eventPluginsDelete($aPlugins) {
        $this->Plugin_Delete($aPlugins);
    }

    protected function _eventPluginsAdd() {
        if ($aZipFile = $this->GetUploadedFile('plugin_arc')) {
            if ($sPackFile = F::File_MoveUploadedFile($aZipFile['tmp_name'], $aZipFile['name'] . '/' . $aZipFile['name'])) {
                $this->Plugin_UnpackPlugin($sPackFile);
                F::File_RemoveDir(dirname($sPackFile));
            }
        }
        $this->_setTitle($this->Lang_Get('action.admin.plugins_title'));
        $this->SetTemplateAction('site/plugins_add');
        $this->Viewer_Assign('sMode', 'add');
        $this->Lang_AddLangJs(array('action.admin.form_choose_file', 'action.admin.form_no_file_selected'));
    }

    /**********************************************************************************/

    protected function EventPages() {
        $this->_setTitle($this->Lang_Get('action.admin.pages_title'));
        // * Получаем и загружаем список всех страниц
        $aPages = $this->Page_GetPages();
        if (count($aPages) == 0 && $this->Page_GetCountPage()) {
            $this->Page_SetPagesPidToNull();
            $aPages = $this->Page_GetPages();
        }
        $this->Viewer_Assign('aPages', $aPages);
        if ($this->GetParam(0) == 'add') {
            $this->_eventPagesEdit('add');
        } elseif ($this->GetParam(0) == 'edit') {
            $this->_eventPagesEdit('edit');
        } else {
            $this->_eventPagesList();
        }
    }

    protected function _eventPagesList() {
        /**
         * Обработка удаления страницы
         * Замечание: если используется тип таблиц MyISAM, а InnoDB то возможно некорректное удаление вложенных страниц
         * TODO: Надо проверить, что там может быть некорректного и поправить
         */
        if ($this->GetParam(0) == 'delete') {
            $this->Security_ValidateSendForm();
            if ($this->Page_deletePageById($this->GetParam(1))) {
                $this->Message_AddNotice($this->Lang_Get('action.admin.pages_admin_action_delete_ok', true));
                Router::Location('admin/pages/');
            } else {
                $this->Message_AddError($this->Lang_Get('action.admin.pages_admin_action_delete_error'), $this->Lang_Get('error'));
            }
        }

        /**
         * Обработка изменения сортировки страницы
         */
        if ($this->GetParam(0) == 'sort' && $oPage = $this->Page_GetPageById($this->GetParam(1))) {
            $this->Security_ValidateSendForm();
            $sWay = $this->GetParam(2) == 'down' ? 'down' : 'up';
            $iSortOld = $oPage->getSort();
            if ($oPagePrev = $this->Page_GetNextPageBySort($iSortOld, $oPage->getPid(), $sWay)) {
                $iSortNew = $oPagePrev->getSort();
                $oPagePrev->setSort($iSortOld);
                $this->Page_UpdatePage($oPagePrev);
            } else {
                if ($sWay == 'down') {
                    $iSortNew = $iSortOld - 1;
                } else {
                    $iSortNew = $iSortOld + 1;
                }
            }
            /**
             * Меняем значения сортировки местами
             */
            $oPage->setSort($iSortNew);
            $this->Page_UpdatePage($oPage);
        }
        $this->SetTemplateAction('publications/page_list');
    }

    protected function _eventPagesEdit($sMode) {
        $this->_setTitle($this->Lang_Get('action.admin.pages_title'));
        $this->SetTemplateAction('publications/page_add');
        $this->Viewer_Assign('sMode', $sMode);
        /**
         * Обработка создания новой страницы
         */
        if (isPost('submit_page_save')) {
            if (!getRequest('page_id')) {
                $this->SubmitAddPage();
            }
        }
        /**
         * Обработка показа страницы для редактирования
         */
        if ($this->GetParam(0) == 'edit') {
            if ($oPageEdit = $this->Page_GetPageById($this->GetParam(1))) {
                if (!isPost('submit_page_save')) {
                    $_REQUEST['page_title'] = $oPageEdit->getTitle();
                    $_REQUEST['page_pid'] = $oPageEdit->getPid();
                    $_REQUEST['page_url'] = $oPageEdit->getUrl();
                    $_REQUEST['page_text'] = $oPageEdit->getText();
                    $_REQUEST['page_seo_keywords'] = $oPageEdit->getSeoKeywords();
                    $_REQUEST['page_seo_description'] = $oPageEdit->getSeoDescription();
                    $_REQUEST['page_active'] = $oPageEdit->getActive();
                    $_REQUEST['page_main'] = $oPageEdit->getMain();
                    $_REQUEST['page_sort'] = $oPageEdit->getSort();
                    $_REQUEST['page_auto_br'] = $oPageEdit->getAutoBr();
                    $_REQUEST['page_id'] = $oPageEdit->getId();
                } else {
                    /**
                     * Если отправили форму с редактированием, то обрабатываем её
                     */
                    $this->SubmitEditPage($oPageEdit);
                }
                $this->Viewer_Assign('oPageEdit', $oPageEdit);
            } else {
                $this->Message_AddError($this->Lang_Get('action.admin.pages_edit_notfound'), $this->Lang_Get('error'));
                $this->SetParam(0, null);
            }
        }
    }

    /**
     * Обработка отправки формы при редактировании страницы
     *
     * @param $oPageEdit
     */
    protected function SubmitEditPage($oPageEdit) {
        /**
         * Проверяем корректность полей
         */
        if (!$this->CheckPageFields()) {
            return;
        }
        if ($oPageEdit->getId() == getRequest('page_pid')) {
            $this->Message_AddError($this->Lang_Get('system_error'));
            return;
        }

        /**
         * Обновляем свойства страницы
         */
        $oPageEdit->setActive(getRequest('page_active') ? 1 : 0);
        $oPageEdit->setAutoBr(getRequest('page_auto_br') ? 1 : 0);
        $oPageEdit->setMain(getRequest('page_main') ? 1 : 0);
        $oPageEdit->setDateEdit(date("Y-m-d H:i:s"));
        if (getRequest('page_pid') == 0) {
            $oPageEdit->setUrlFull(getRequest('page_url'));
            $oPageEdit->setPid(null);
        } else {
            $oPageEdit->setPid(getRequest('page_pid'));
            $oPageParent = $this->Page_GetPageById(getRequest('page_pid'));
            $oPageEdit->setUrlFull($oPageParent->getUrlFull() . '/' . getRequest('page_url'));
        }
        $oPageEdit->setSeoDescription(getRequest('page_seo_description'));
        $oPageEdit->setSeoKeywords(getRequest('page_seo_keywords'));
        $oPageEdit->setText(getRequest('page_text'));
        $oPageEdit->setTitle(getRequest('page_title'));
        $oPageEdit->setUrl(getRequest('page_url'));
        $oPageEdit->setSort(getRequest('page_sort'));
        /**
         * Обновляем страницу
         */
        if ($this->Page_UpdatePage($oPageEdit)) {
            $this->Page_RebuildUrlFull($oPageEdit);
            $this->Message_AddNotice($this->Lang_Get('action.admin.pages_edit_submit_save_ok'));
            $this->SetParam(0, null);
            $this->SetParam(1, null);
            Router::Location('admin/pages/');
        } else {
            $this->Message_AddError($this->Lang_Get('system_error'));
        }
    }

    /**
     * Обработка отправки формы добавления новой страницы
     *
     */
    protected function SubmitAddPage() {
        /**
         * Проверяем корректность полей
         */
        if (!$this->CheckPageFields()) {
            return;
        }
        /**
         * Заполняем свойства
         */
        $oPage = Engine::GetEntity('PluginPage_Page');
        $oPage->setActive(getRequest('page_active') ? 1 : 0);
        $oPage->setAutoBr(getRequest('page_auto_br') ? 1 : 0);
        $oPage->setMain(getRequest('page_main') ? 1 : 0);
        $oPage->setDateAdd(date("Y-m-d H:i:s"));
        if (getRequest('page_pid') == 0) {
            $oPage->setUrlFull(getRequest('page_url'));
            $oPage->setPid(null);
        } else {
            $oPage->setPid(getRequest('page_pid'));
            $oPageParent = $this->Page_GetPageById(getRequest('page_pid'));
            $oPage->setUrlFull($oPageParent->getUrlFull() . '/' . getRequest('page_url'));
        }
        $oPage->setSeoDescription(getRequest('page_seo_description'));
        $oPage->setSeoKeywords(getRequest('page_seo_keywords'));
        $oPage->setText(getRequest('page_text'));
        $oPage->setTitle(getRequest('page_title'));
        $oPage->setUrl(getRequest('page_url'));
        if (getRequest('page_sort')) {
            $oPage->setSort(getRequest('page_sort'));
        } else {
            $oPage->setSort($this->Page_GetMaxSortByPid($oPage->getPid()) + 1);
        }
        /**
         * Добавляем страницу
         */
        if ($this->Page_AddPage($oPage)) {
            $this->Message_AddNotice($this->Lang_Get('action.admin.pages_create_submit_save_ok'));
            $this->SetParam(0, null);
            Router::Location('admin/pages/');
        } else {
            $this->Message_AddError($this->Lang_Get('system_error'));
        }
    }

    /**
     * Проверка полей на корректность
     *
     * @return unknown
     */
    protected function CheckPageFields() {
        $this->Security_ValidateSendForm();

        $bOk = true;
        /**
         * Проверяем есть ли заголовок топика
         */
        if (!func_check(getRequest('page_title', null, 'post'), 'text', 2, 200)) {
            $this->Message_AddError($this->Lang_Get('action.admin.pages_create_title_error'), $this->Lang_Get('error'));
            $bOk = false;
        }
        /**
         * Проверяем есть ли заголовок топика, с заменой всех пробельных символов на "_"
         */
        $pageUrl = preg_replace("/\s+/", '_', (string)getRequest('page_url', null, 'post'));
        $_REQUEST['page_url'] = $pageUrl;
        if (!func_check(getRequest('page_url', null, 'post'), 'login', 1, 50)) {
            $this->Message_AddError($this->Lang_Get('action.admin.pages_create_url_error'), $this->Lang_Get('error'));
            $bOk = false;
        }
        /**
         * Проверяем на счет плохих УРЛов
         */
        /*if (in_array(getRequest('page_url',null,'post'),$this->aBadPageUrl)) {
            $this->Message_AddError($this->Lang_Get('action.admin.pages_create_url_error_bad').' '.join(',',$this->aBadPageUrl),$this->Lang_Get('error'));
            $bOk=false;
        }*/
        /**
         * Проверяем есть ли содержание страницы
         */
        if (!func_check(getRequest('page_text', null, 'post'), 'text', 1, 50000)) {
            $this->Message_AddError($this->Lang_Get('action.admin.pages_create_text_error'), $this->Lang_Get('error'));
            $bOk = false;
        }
        /**
         * Проверяем страницу в которую хотим вложить
         */
        if (getRequest('page_pid') != 0 && !($oPageParent = $this->Page_GetPageById(getRequest('page_pid')))) {
            $this->Message_AddError($this->Lang_Get('action.admin.pages_create_parent_page_error'), $this->Lang_Get('error'));
            $bOk = false;
        }
        /**
         * Проверяем сортировку
         */
        if (getRequest('page_sort') && !is_numeric(getRequest('page_sort'))) {
            $this->Message_AddError($this->Lang_Get('action.admin.pages_create_sort_error'), $this->Lang_Get('error'));
            $bOk = false;
        }
        /**
         * Выполнение хуков
         */
        $this->Hook_Run('check_page_fields', array('bOk' => &$bOk));

        return $bOk;
    }


    /**********************************************************************************/

    protected function EventBlogs() {
        $this->_setTitle($this->Lang_Get('action.admin.blogs_title'));
        $this->SetTemplateAction('publications/blogs_list');

        $sMode = 'all';

        $sCmd = $this->GetPost('cmd');
        if ($sCmd == 'delete') {
            $this->_blogDelete();
        }

        // * Передан ли номер страницы
        $nPage = $this->_getPageNum();

        if ($this->GetParam(1) && !strstr($this->GetParam(1), 'page')) $sMode = $this->GetParam(1);

        $aFilter = array();
        if ($sMode && $sMode != 'all') {
            $aFilter['type'] = $sMode;
        }

        $aResult = $this->Blog_GetBlogsByFilter($aFilter, '', $nPage, Config::Get('admin.items_per_page'));
        $aPaging = $this->Viewer_MakePaging($aResult['count'], $nPage, Config::Get('admin.items_per_page'), 4,
            Router::GetPath('admin') . 'blogs/list/' . $sMode);

        $aBlogTypes = $this->Blog_GetBlogTypes();

        $this->Viewer_Assign('nBlogsTotal', $aResult['count']);
        $this->Viewer_Assign('aBlogTypes', $aBlogTypes);
        $this->Viewer_Assign('aBlogs', $aResult['collection']);

        $this->Viewer_Assign('sMode', $sMode);
        $this->Viewer_Assign('aPaging', $aPaging);
    }

    protected function _eventBlogsDelete() {
        $bOk = false;
        $nBlogId = $this->_GetRequestCheck('blog_id');
        if ($nBlogId && ($oBlog = $this->Blog_GetBlogById($nBlogId))) {
            $bOk = $this->PluginAceadminpanel_Admin_DelBlog($nBlogId);
        }
        if ($bOk) {
            $this->Message_AddNotice($this->Lang_Get('action.admin.action_ok'), 'blog_del');
        } else {
            $this->Message_AddError($this->Lang_Get('action.admin.action_err'), 'blog_del');
        }
        $this->_gotoBackPage();
    }

    /**********************************************************************************/

    protected function EventTopics() {
        $this->_setTitle($this->Lang_Get('action.admin.topics_title'));
        $this->SetTemplateAction('publications/topics_list');

        $sCmd = $this->GetPost('cmd');
        if ($sCmd == 'delete') {
            $this->_topicDelete();
        } else {
            // * Передан ли номер страницы
            $nPage = $this->_getPageNum();
        }

        $aResult = $this->Topic_GetTopicsByFilter(array(), $nPage, Config::Get('admin.items_per_page'));
        $aPaging = $this->Viewer_MakePaging($aResult['count'], $nPage, Config::Get('admin.items_per_page'), 4,
            Router::GetPath('admin') . 'topics/');

        $this->Viewer_Assign('aTopics', $aResult['collection']);
        $this->Viewer_Assign('aPaging', $aPaging);
    }

    /**********************************************************************************/

    protected function EventComments() {
        $this->_setTitle($this->Lang_Get('action.admin.comments_title'));
        $this->SetTemplateAction('publications/comments_list');

        $sCmd = $this->GetPost('cmd');
        if ($sCmd == 'delete') {
            $this->_commentDelete();
        }

        // * Передан ли номер страницы
        $nPage = $this->_getPageNum();

        $aResult = $this->Comment_GetCommentsByFilter(array(), '', $nPage, Config::Get('admin.items_per_page'));
        $aPaging = $this->Viewer_MakePaging($aResult['count'], $nPage, Config::Get('admin.items_per_page'), 4,
            Router::GetPath('admin') . 'comments/');

        $this->Viewer_Assign('aComments', $aResult['collection']);
        $this->Viewer_Assign('aPaging', $aPaging);
    }

    /**********************************************************************************/

    protected function EventUsers() {
        $this->_setTitle($this->Lang_Get('action.admin.users_title'));
        $this->SetTemplateAction('users/users');

        $sMode = $this->_getMode(0, 'list', array('list', 'admins'));

        if (($sCmd = $this->GetPost('adm_user_cmd'))) {
            if ($sCmd == 'adm_ban_user') {
                $sUsersList = $this->GetPost('adm_user_list');
                $nBanDays = $this->GetPost('ban_days');
                $sBanComment = $this->GetPost('ban_comment');

                $sIp = $this->GetPost('user_ban_ip1');
                if ($sIp) {
                    $sIp .= $this->GetPost('user_ban_ip2', '0')
                        . '.' . $this->GetPost('user_ban_ip3', '0')
                        . '.' . $this->GetPost('user_ban_ip4', '0');
                }

                if ($sUsersList) {
                    $aUsersId = F::Array_Str2Array($sUsersList);
                    $this->_eventUsersCmdBan($aUsersId, $nBanDays, $sBanComment);
                } elseif ($sIp) {
                    $this->_eventIpsCmdBan($sIp, $nBanDays, $sBanComment);
                }
            } elseif ($sCmd == 'adm_unban_user') {
                $aUsersId = $this->GetPost('adm_user_list');
                $this->_eventUsersCmdUnban($aUsersId);
            } elseif ($sCmd == 'adm_user_setadmin') {
                $this->_eventUsersCmdSetAdministrator();
            } elseif ($sCmd == 'adm_user_unsetadmin') {
                $this->_eventUsersCmdUnsetAdministrator();
            } elseif ($sCmd == 'adm_del_user') {
                if ($this->_eventUsersCmdDelete()) {
                    Router::Location('admin/users/');
                }
            } elseif ($sCmd == 'adm_user_message') {
                $this->_eventUsersCmdMessage();
            } elseif ($sCmd == 'adm_user_activate') {
                $this->_eventUsersCmdActivate();
            }
            Router::ReturnBack(true);
        }

        if ($this->GetPost('adm_userlist_filter')) {
            $this->_eventUsersFilter();
        }

        if ($sMode == 'profile') {
            // админ-профиль юзера
            return $this->_eventUsersProfile();
        } elseif ($this->GetParam(0) == 'admins' && $this->GetParam(1) == 'del') {
            $this->EventUsersDelAdministrator();
        } else {
            $this->_eventUsersList($sMode);
        }
        $this->Viewer_Assign('sMode', $sMode);
        $this->Viewer_Assign('nCountUsers', $this->User_GetCountUsers());
        $this->Viewer_Assign('nCountAdmins', $this->User_GetCountAdmins());
    }

    protected function _eventUsersCmdBan($aUsersId, $nDays, $sComment) {
        if ($aUsersId) {
            if ($this->Admin_BanUsers($aUsersId, $nDays, $sComment)) {
                $this->Message_AddNotice($this->Lang_Get('action.admin.action_ok'), null, true);
                return true;
            } else {
                $this->Message_AddError($this->Lang_Get('action.admin.action_err'), null, true);
            }
        }
        return false;
    }

    protected function _eventUsersCmdUnban($aUsersId) {
        if ($aUsersId) {
            $aId = F::Array_Str2ArrayInt($aUsersId, ',', true);
            if ($this->Admin_UnbanUsers($aId)) {
                $this->Message_AddNotice($this->Lang_Get('action.admin.action_ok'), null, true);
                return true;
            } else {
                $this->Message_AddError($this->Lang_Get('action.admin.action_err'), null, true);
            }
        }
        return false;
    }

    protected function _eventIpsCmdBan($sIp, $nDays, $sComment) {
        $aIp = explode('.', $sIp) + array(0, 0, 0, 0);
        if ($aIp[0] < 1 || $aIp[0] > 254) {
            // error - first part cannot be empty
        } else {
            $sIp1 = '';
            foreach ($aIp as $sPart) {
                $n = intval($sPart);
                if ($n < 0 || $n >= 255) $n = 0;
                if ($sIp1) $sIp1 .= '.';
                $sIp1 .= $n;
            }
            $sIp2 = '';
            foreach ($aIp as $sPart) {
                $n = intval($sPart);
                if ($n <= 0 || $n >= 255) $n = 255;
                if ($sIp2) $sIp2 .= '.';
                $sIp2 .= $n;
            }
            if ($this->Admin_SetBanIp($sIp1, $sIp2, $nDays, $sComment)) {
                $this->Message_AddNotice($this->Lang_Get('action.admin.action_ok'), null, true);
                return true;
            }
        }
        $this->Message_AddError($this->Lang_Get('action.admin.action_err'), null, true);
        return false;
    }

    protected function _eventUsersList($sMode) {
        $this->SetTemplateAction('users/users_list');
        // * Передан ли номер страницы
        $nPage = $this->_getPageNum();

        $aFilter = array();
        $sData = $this->Session_Get('adm_userlist_filter');
        if ($sData) {
            $aFilter = @unserialize($sData);
            if (!is_array($aFilter)) {
                $aFilter = array();
            }
        }

        if ($sMode == 'admins') {
            $aFilter['admin'] = 1;
        }

        $aResult = $this->User_GetUsersByFilter($aFilter, '', $nPage, Config::Get('admin.items_per_page'));
        $aPaging = $this->Viewer_MakePaging($aResult['count'], $nPage, Config::Get('admin.items_per_page'), 4,
            Router::GetPath('admin') . 'users/');

        foreach ($aFilter as $sKey => $xVal) {
            if ($sKey == 'ip') {
                if (!$xVal || ($xVal == '*.*.*.*') || ($xVal == '0.0.0.0')) {
                    unset($aFilter[$sKey]);
                } else {
                    $aIp = explode('.', $xVal) + array('*', '*', '*', '*');
                    foreach ($aIp as $n => $sPart) {
                        if ($sPart == '*') {
                            $aIp[$n] = '';
                        } else {
                            $aIp[$n] = $sPart;
                        }
                    }
                    $aFilter[$sKey] = $aIp;
                }
            } elseif ($sKey == 'admin' || !$xVal) {
                unset($aFilter[$sKey]);
            }
        }
        $this->Viewer_Assign('aUsers', $aResult['collection']);
        $this->Viewer_Assign('aPaging', $aPaging);
        $this->Viewer_Assign('aFilter', $aFilter);
    }

    protected function _eventUsersCmdSetAdministrator() {
        $aUserLogins = F::Str2Array($this->GetPost('user_login_admin'), ',', true);
        if ($aUserLogins)
            foreach ($aUserLogins as $sUserLogin) {
                if (!$sUserLogin || !($oUser = $this->User_GetUserByLogin($sUserLogin))) {
                    $this->Message_AddError($this->Lang_Get('action.admin.user_not_found', $sUserLogin));
                } elseif ($oUser->IsBanned()) {
                    $this->Message_AddError($this->Lang_Get('action.admin.cannot_banned_admin'));
                } elseif ($oUser->IsAdministrator()) {
                    $this->Message_AddError($this->Lang_Get('action.admin.already_added'));
                } else {
                    if ($this->Admin_SetAdministrator($oUser->GetId())) {
                        $this->Message_AddNotice($this->Lang_Get('action.admin.saved_ok'));
                    } else {
                        $this->Message_AddError($this->Lang_Get('action.admin.saved_err'));
                    }
                }
            }
        Router::ReturnBack(true);
    }

    protected function _eventUsersCmdUnsetAdministrator() {
        $aUserLogins = F::Str2Array($this->GetPost('users_list'), ',', true);
        if ($aUserLogins)
            foreach ($aUserLogins as $sUserLogin) {
                if (!$sUserLogin || !($oUser = $this->User_GetUserByLogin($sUserLogin))) {
                    $this->Message_AddError($this->Lang_Get('action.admin.user_not_found', $sUserLogin), 'admins:delete');
                } else {
                    if (mb_strtolower($sUserLogin) == 'admin') {
                        $this->Message_AddError($this->Lang_Get('action.admin.cannot_with_admin'), 'admins:delete');
                    } elseif ($this->Admin_UnsetAdministrator($oUser->GetId())) {
                        $this->Message_AddNotice($this->Lang_Get('action.admin.saved_ok'), 'admins:delete');
                    } else {
                        $this->Message_AddError($this->Lang_Get('action.admin.saved_err'), 'admins:delete');
                    }
                }
            }
        Router::ReturnBack(true);
    }

    protected function _eventUsersProfile() {
        $nUserId = $this->GetParam(1);
        $oUserProfile = $this->User_GetUserById($nUserId);
        if (!$oUserProfile) {
            $this->Message_AddError($this->Lang_Get('action.admin.user_not_found'));
            return;
        }

        $sMode = $this->GetParam(2);
        //$aUserVoteStat = $this->User_GetUserVoteStats($oUserProfile->getId());

        if ($sMode == 'topics') {
            $this->EventUsersProfileTopics($oUserProfile);
        } elseif ($sMode == 'blogs') {
            $this->EventUsersProfileBlogs($oUserProfile);
        } elseif ($sMode == 'comments') {
            $this->EventUsersProfileComments($oUserProfile);
        } elseif ($sMode == 'voted') {
            $this->EventUsersProfileVotedBy($oUserProfile);
        } elseif ($sMode == 'votes') {
            $this->EventUsersProfileVotesFor($oUserProfile);
        } elseif ($sMode == 'ips') {
            $this->EventUsersProfileIps($oUserProfile);
        } else {
            $sMode = 'info';
            $this->_eventUsersProfileInfo($oUserProfile);
        }

        $this->Viewer_Assign('sMode', $sMode);
        $this->Viewer_Assign('oUserProfile', $oUserProfile);
        //$this->Viewer_Assign('aUserVoteStat', $aUserVoteStat);
        //$this->Viewer_Assign('nParamVoteValue', $this->aConfig['vote_value']);

    }

    protected function _eventUsersProfileInfo($oUserProfile) {
        $this->SetTemplateAction('users/users_profile_info');
    }

    protected function _eventUsersFilter() {
        $aFilter = array();

        if (($sUserLogin = $this->GetPost('user_filter_login'))) {
            $aFilter['login'] = $sUserLogin;
        } else {
            $aFilter['login'] = null;
        }

        if (($sUserEmail = $this->GetPost('user_filter_email'))) {
            $aFilter['email'] = $sUserEmail;
        } else {
            $aFilter['email'] = null;
        }

        $aUserFilterIp = array('*', '*', '*', '*');
        if (is_numeric($n = $this->GetPost('user_filter_ip1')) && $n < 256) {
            $aUserFilterIp[0] = $n;
        }
        if (is_numeric($n = $this->GetPost('user_filter_ip2')) && $n < 256) {
            $aUserFilterIp[1] = $n;
        }
        if (is_numeric($n = $this->GetPost('user_filter_ip3')) && $n < 256) {
            $aUserFilterIp[2] = $n;
        }
        if (is_numeric($n = $this->GetPost('user_filter_ip4')) && $n < 256) {
            $aUserFilterIp[3] = $n;
        }

        $sUserFilterIp = implode('.', $aUserFilterIp);
        if ($sUserFilterIp != '*.*.*.*') {
            $aFilter['ip'] = $sUserFilterIp;
        } else {
            $aFilter['ip'] = null;
        }

        if (($s = getRequest('user_filter_regdate'))) {
            if (preg_match('/(\d{4})(\-(\d{1,2})){0,1}(\-(\d{1,2})){0,1}/', $s, $aMatch)) {
                if (isset($aMatch[1])) {
                    $sUserRegDate = $aMatch[1];
                    if (isset($aMatch[3])) {
                        $sUserRegDate .= '-' . sprintf('%02d', $aMatch[3]);
                        if (isset($aMatch[5])) {
                            $sUserRegDate .= '-' . sprintf('%02d', $aMatch[5]);
                        }
                    }
                }
            }
            if ($sUserRegDate) {
                $aFilter['regdate'] = $sUserRegDate;
            } else {
                $aFilter['regdate'] = null;
            }
        }
        $this->Session_Set('adm_userlist_filter', serialize($aFilter));
    }

    /**********************************************************************************/

    protected function EventInvites() {
        $this->_setTitle($this->Lang_Get('action.admin.invites_title'));
        $this->SetTemplateAction('users/invites_list');

        $sMode = $this->GetParam(0);
        if ($sMode == 'add') {
            $this->_eventInvitesAdd();
        } else {
            $sMode = 'list';
            $this->_eventInvitesList();
        }

        $this->Viewer_Assign('sMode', $sMode);

        if ($this->oUserCurrent->isAdministrator()) {
            $iCountInviteAvailable = -1;
        } else {
            $iCountInviteAvailable = $this->User_GetCountInviteAvailable($this->oUserCurrent);
        }
        $this->Viewer_Assign('iCountInviteAvailable', $iCountInviteAvailable);
        $this->Viewer_Assign('iCountInviteUsed', $this->User_GetCountInviteUsed($this->oUserCurrent->getId()));
    }

    protected function _eventInvitesList() {
        if (getRequest('action', null, 'post') == 'delete') {
            $this->_eventInvitesDelete();
        }

        $nPage = $this->_getPageNum();

        // Получаем список инвайтов
        $aResult = $this->Admin_GetInvites($nPage, Config::Get('admin.items_per_page'));
        $aInvites = $aResult['collection'];

        // Формируем постраничность
        $aPaging = $this->Viewer_MakePaging($aResult['count'], $nPage, Config::Get('admin.items_per_page'), 4, Router::GetPath('admin') . 'invites');
        $this->Viewer_Assign('aPaging', $aPaging);
        $this->Viewer_Assign('aInvites', $aInvites);
        $this->Viewer_Assign('iCount', $aResult['count']);
    }

    protected function _eventInvitesDelete() {
        $this->Security_ValidateSendForm();

        $aIds = array();
        foreach ($_POST as $sKey => $sVal) {
            if ((substr($sKey, 0, 7) == 'invite_') && ($nId = intval(substr($sKey, 7)))) {
                $aIds[] = $nId;
            }
        }
        if ($aIds) {
            $nResult = $this->Admin_DeleteInvites($aIds);
            $this->Message_AddNotice($this->Lang_Get('action.admin.invaite_deleted', array('num' => $nResult)));
        }
        Router::ReturnBack(true);
    }

    /**********************************************************************************/

    protected function EventBanlist() {
        $this->_setTitle($this->Lang_Get('action.admin.banlist_title'));
        $sMode = $this->_getMode(0, 'ids');
        $nPage = $this->_getPageNum();

        if ($sCmd = $this->GetPost('adm_user_cmd')) {
            $this->_eventBanListCmd($sCmd);
        }
        if ($sMode == 'ips') {
            $this->_eventBanlistIps($nPage);
        } else {
            $sMode = 'ids';
            $this->_eventBanlistIds($nPage);
        }
        $this->Viewer_Assign('sMode', $sMode);
    }

    protected function _eventBanListCmd($sCmd) {
        if ($sCmd == 'adm_user_ban') {
            $sUsersList = $this->GetPost('user_login');
            $nBanDays = $this->GetPost('ban_days');
            $sBanComment = $this->GetPost('ban_comment');

            $sIp = $this->GetPost('user_ban_ip1');
            if ($sIp) {
                $sIp .= $this->GetPost('user_ban_ip2', '0')
                    . '.' . $this->GetPost('user_ban_ip3', '0')
                    . '.' . $this->GetPost('user_ban_ip4', '0');
            }

            if ($sUsersList) {
                // здесь получаем логины юзеров
                $aUsersLogin = F::Array_Str2Array($sUsersList);
                // по логинам получаем список юзеров
                $aUsers = $this->User_GetUsersByFilter(array('login' => $aUsersLogin), '', 1, 100, array());
                if ($aUsers) {
                    // и их баним
                    $this->_eventUsersCmdBan($aUsers['collection'], $nBanDays, $sBanComment);
                }
            } elseif ($sIp) {
                $this->_eventIpsCmdBan($sIp, $nBanDays, $sBanComment);
            }
        } elseif ($sCmd == 'adm_unsetban_ip') {
            $aId = F::Array_Str2ArrayInt($this->GetPost('bans_list'), ',', true);
            $this->Admin_UnsetBanIp($aId);
        } elseif ($sCmd == 'adm_unsetban_user') {
            $aUsersId = F::Array_Str2ArrayInt($this->GetPost('bans_list'), ',', true);
            $this->_eventUsersCmdUnban($aUsersId);
        }
        Router::ReturnBack(true);
    }

    protected function _eventBanlistIds($nPage) {
        $this->SetTemplateAction('users/banlist_ids');

        // Получаем список забаненных юзеров
        $aResult = $this->Admin_GetUsersBanList($nPage, Config::Get('admin.items_per_page'));

        // Формируем постраничность
        $aPaging = $this->Viewer_MakePaging(
            $aResult['count'], $nPage, Config::Get('admin.items_per_page'), 4, Router::GetPath('admin') . 'banlist/ids/'
        );
        $this->Viewer_Assign('aPaging', $aPaging);
        $this->Viewer_Assign('aUserList', $aResult['collection']);
    }

    protected function _eventBanlistIps($nPage) {
        $this->SetTemplateAction('users/banlist_ips');

        // Получаем список забаненных ip-адресов
        $aResult = $this->Admin_GetIpsBanList($nPage, Config::Get('admin.items_per_page'));

        // Формируем постраничность
        $aPaging = $this->Viewer_MakePaging(
            $aResult['count'], $nPage, Config::Get('admin.items_per_page'), 4, Router::GetPath('admin') . 'banlist/ips/'
        );
        $this->Viewer_Assign('aPaging', $aPaging);
        $this->Viewer_Assign('aIpsList', $aResult['collection']);
    }

    /**********************************************************************************/

    protected function EventSkins() {
        $this->_setTitle($this->Lang_Get('action.admin.skins_title'));
        $this->SetTemplateAction('site/skins');

        // Определяем скин и тему основного сайта (не админки)
        $sSiteSkin = Config::Get('view.skin', Config::DEFAULT_CONFIG_INSTANCE);
        $sSiteTheme = Config::Get('skin.' . $sSiteSkin . '.config.view.theme');

        if (!$sSiteTheme && F::File_Exists($sFile = Config::Get('path.skins.dir') . $sSiteSkin . '/settings/config/config.php')) {
            $aSkinConfig = F::IncludeFile($sFile, false, true);
            if (isset($aSkinConfig['view']) && isset($aSkinConfig['view']['theme'])) {
                $sSiteTheme = $aSkinConfig['view']['theme'];
            } elseif (isset($aSkinConfig['view.theme'])) {
                $sSiteTheme = $aSkinConfig['view.theme'];
            }
        }

        $sMode = $this->GetParam(0);
        if ($sMode == 'adm') {
            $aFilter = array('type' => 'adminpanel');
        } elseif ($sMode == 'all') {
            $aFilter = array('type' => '');
        } else {
            $sMode = 'site';
            $aFilter = array('type' => 'site');
        }
        if ($this->GetPost('submit_skins_del')) {
            // Удаление плагинов
            $this->_eventSkinsDelete();
        } elseif ($sSkin = $this->GetPost('skin_activate')) {
            $this->_eventSkinActivate($sSkin);
        } elseif ($sTheme = $this->GetPost('theme_activate')) {
            $this->_eventSkinThemeActivate($sSiteSkin, $sTheme);
        }

        $aSkins = $this->Skin_GetSkinsList($aFilter);
        $oActiveSkin = null;
        foreach ($aSkins as $sKey => $oSkin) {
            if ($oSkin->GetIsActive()) {
                $oActiveSkin = $oSkin;
                unset($aSkins[$sKey]);
            }
        }

        $this->Viewer_Assign('sSiteSkin', $sSiteSkin);
        $this->Viewer_Assign('sSiteTheme', $sSiteTheme);

        $this->Viewer_Assign('oActiveSkin', $oActiveSkin);
        $this->Viewer_Assign('aSkins', $aSkins);
        $this->Viewer_Assign('sMode', $sMode);
    }

    protected function _eventSkinActivate($sSkin) {
        $aConfig = array('view.skin' => $sSkin);
        Config::WriteCustomConfig($aConfig);
        Router::Location('admin/skins/');
    }

    protected function _eventSkinThemeActivate($sSkin, $sTheme) {
        $aConfig = array('skin.' . $sSkin . '.config.view.theme' => $sTheme);
        Config::WriteCustomConfig($aConfig);
        Router::Location('admin/skins/');
    }

    /**********************************************************************************/

    /**
     * View logs
     */
    protected function EventLogs() {
        $sMode = $this->GetParam(0);
        if ($sMode == 'sqlerrors') {
            $sLogFile = Config::Get('sys.logs.dir') . Config::Get('sys.logs.sql_error_file');
        } elseif ($sMode == 'sql') {
            $sLogFile = Config::Get('sys.logs.dir') . Config::Get('sys.logs.sql_query_file');
        } else {
            $sMode = 'errors';
            $sLogFile = Config::Get('sys.logs.dir') . F::ERROR_LOG;
        }

        if (!is_null($this->GetPost('submit_logs_del'))) {
            $this->_eventLogsErrorDelete($sLogFile);
        }

        $sLogTxt = F::File_GetContents($sLogFile);
        if ($sMode == 'sqlerrors') {
            $this->_setTitle($this->Lang_Get('action.admin.logs_sql_errors_title'));
            $this->SetTemplateAction('logs/sql_errors');
            $this->_eventLogsSqlErrors($sLogTxt);
        } elseif ($sMode == 'sql') {
            $this->_setTitle($this->Lang_Get('action.admin.logs_sql_title'));
            $this->SetTemplateAction('logs/sql_log');
            $this->_eventLogsSql($sLogTxt);
        } else {
            $this->_setTitle($this->Lang_Get('action.admin.logs_errors_title'));
            $this->SetTemplateAction('logs/errors');
            $this->_eventLogsErrors($sLogTxt);
        }

        $this->Viewer_Assign('sMode', $sMode);
        $this->Viewer_Assign('sLogTxt', $sLogTxt);
    }

    protected function _eventLogsErrorDelete($sLogFile) {
        F::File_Delete($sLogFile);
        //Router::Location();
    }

    protected function _parseLog($sLogTxt) {
        $aLogs = array();
        if (preg_match_all('/\[LOG\:(?<id>[\d\-\.\,]+)\]\[(?<date>[\d\-\s\:]+)\].*\[\[(?<text>.*)\]\]/siuU', $sLogTxt, $aM, PREG_PATTERN_ORDER)) {
            foreach ($aM[0] as $nRec => $sVal) {
                $aRec = array(
                    'id' => $aM['id'][$nRec],
                    'date' => $aM['date'][$nRec],
                    'text' => $aM['text'][$nRec],
                );
                array_unshift($aLogs, $aRec);
            }
        }
        return $aLogs;
    }

    /**
     * Runtime errors of engine
     *
     * @param $sLogTxt
     */
    protected function _eventLogsErrors($sLogTxt) {
        $aLogs = $this->_parseLog($sLogTxt);
        foreach ($aLogs as $nRec => $aRec) {
            if ($n = strpos($aRec['text'], '---')) {
                $aRec['text'] = nl2br(trim(substr($aRec['text'], 0, $n)));
            } else {
                $aRec['text'] = nl2br(trim($aRec['text']));
            }
            $aLogs[$nRec] = $aRec;
        }

        $this->Viewer_Assign('aLogs', $aLogs);
    }

    protected function _eventLogsSqlErrors($sLogTxt) {
        $aLogs = $this->_parseLog($sLogTxt);
        foreach ($aLogs as $nRec => $aRec) {
            if ($n = strpos($aRec['text'], '---')) {
                $aRec['info'] = trim(substr($aRec['text'], $n + 3));
                $aRec['sql'] = '';
                if (strpos($aRec['info'], 'Array') !== false && preg_match('/\[query\]\s*\=\>(.*)\[context\]\s*\=\>(.*)$/siuU', $aRec['info'], $aM)) {
                    $aRec['sql'] = trim($aM[1]);
                }
                $aRec['text'] = trim(substr($aRec['text'], 0, $n));
            } else {
                $aRec['info'] = '';
                $aRec['sql'] = '';
                $aRec['text'] = trim($aRec['text']);
            }
            $aLogs[$nRec] = $aRec;
        }

        $this->Viewer_Assign('aLogs', $aLogs);
    }

    protected function _eventLogsSql($sLogTxt) {
        $aLogs = $this->_parseLog($sLogTxt);
        foreach ($aLogs as $nRec => $aRec) {
            if (preg_match('/--\s(\d+)\s(\ws);(.*)$/U', $aRec['text'], $aM, PREG_OFFSET_CAPTURE)) {
                $aRec['text'] = trim(substr($aRec['text'], 0, $aM[0][1]));
                $aRec['time'] = $aM[1][0] . ' ' . $aM[2][0];
                $aRec['result'] = trim($aM[3][0]);
                if (($n = strpos($aRec['result'], 'returned')) !== false) {
                    $aRec['result'] = trim(substr($aRec['result'], 8));
                }
            } else {
                $aRec['text'] = trim($aRec['text']);
                $aRec['time'] = 'unknown';
                $aRec['result'] = '';
            }
            $aLogs[$nRec] = $aRec;
        }

        $this->Viewer_Assign('aLogs', $aLogs);
    }

    /**********************************************************************************/

    protected function EventReset() {
        $this->_setTitle($this->Lang_Get('action.admin.reset_title'));
        $this->SetTemplateAction('tools/reset');

        if ($this->GetPost('adm_reset_submit')) {
            if ($this->GetPost('adm_cache_clear_data')) $this->Cache_Clean();
            if ($this->GetPost('adm_cache_clear_assets')) $this->Viewer_ClearAssetsFiles();
            if ($this->GetPost('adm_cache_clear_smarty')) $this->Viewer_ClearSmartyFiles();
            if ($this->GetPost('adm_reset_config_data')) $this->_eventResetCustomConfig();
            $this->Message_AddNotice($this->Lang_Get('action.admin.action_ok'), null, true);
            Router::Location('admin/reset');
        }
    }

    protected function _eventResetCustomConfig() {
        //
    }

    /**********************************************************************************/

    /**
     * Перестроение дерева комментариев, актуально при $config['module']['comment']['use_nested'] = true;
     *
     */
    protected function EventCommentsTree() {
        $this->_setTitle($this->Lang_Get('action.admin.comments_tree_title'));
        $this->SetTemplateAction('tools/comments_tree');
        if (isPost('comments_tree_submit')) {
            $this->Security_ValidateSendForm();
            set_time_limit(0);
            $this->Comment_RestoreTree();
            $this->Cache_Clean();

            $this->Message_AddNotice($this->Lang_Get('comments_tree_restored'), $this->Lang_Get('attention'));
            $this->Viewer_Assign('bActionEnable', false);
        } else {
            if (Config::Get('module.comment.use_nested')) {
                $this->Viewer_Assign('sMessage', $this->Lang_Get('action.admin.comments_tree_message'));
                $this->Viewer_Assign('bActionEnable', true);
            } else {
                $this->Viewer_Assign('sMessage', $this->Lang_Get('action.admin.comments_tree_disabled'));
                $this->Viewer_Assign('bActionEnable', false);
            }
        }
    }

    /**********************************************************************************/

    /**
     * Пересчет счетчика избранных
     *
     */
    protected function EventRecalculateFavourites() {
        $this->_setTitle($this->Lang_Get('action.admin.recalcfavourites_title'));
        $this->SetTemplateAction('tools/recalcfavourites');
        if (isPost('recalcfavourites_submit')) {
            $this->Security_ValidateSendForm();
            set_time_limit(0);
            $this->Comment_RecalculateFavourite();
            $this->Topic_RecalculateFavourite();
            $this->Cache_Clean();

            $this->Message_AddNotice($this->Lang_Get('action.admin.favourites_recalculated'), $this->Lang_Get('attention'));
            $this->Viewer_Assign('bActionEnable', false);
        } else {
            $this->Viewer_Assign('sMessage', $this->Lang_Get('action.admin.recalcfavourites_message'));
            $this->Viewer_Assign('bActionEnable', true);
        }
    }

    /**********************************************************************************/

    /**
     * Пересчет счетчика голосований
     */
    protected function EventRecalculateVotes() {
        $this->_setTitle($this->Lang_Get('action.admin.recalcvotes_title'));
        $this->SetTemplateAction('tools/recalcvotes');
        if (isPost('recalcvotes_submit')) {
            $this->Security_ValidateSendForm();
            set_time_limit(0);
            $this->Topic_RecalculateVote();
            $this->Cache_Clean();

            $this->Message_AddNotice($this->Lang_Get('action.admin.votes_recalculated'), $this->Lang_Get('attention'));
        } else {
            $this->Viewer_Assign('sMessage', $this->Lang_Get('action.admin.recalcvotes_message'));
            $this->Viewer_Assign('bActionEnable', true);
        }
    }

    /**********************************************************************************/

    /**
     * Пересчет количества топиков в блогах
     */
    protected function EventRecalculateTopics() {
        $this->_setTitle($this->Lang_Get('action.admin.recalctopics_title'));
        $this->SetTemplateAction('tools/recalctopics');
        if (isPost('recalctopics_submit')) {
            $this->Security_ValidateSendForm();
            set_time_limit(0);
            $this->Blog_RecalculateCountTopic();
            $this->Cache_Clean();

            $this->Message_AddNotice($this->Lang_Get('action.admin.topics_recalculated'), $this->Lang_Get('attention'));
        } else {
            $this->Viewer_Assign('sMessage', $this->Lang_Get('action.admin.recalctopics_message'));
            $this->Viewer_Assign('bActionEnable', true);
        }
    }

    /**
     * Контроль БД
     */
    protected function EventCheckDb() {
        $this->_setTitle($this->Lang_Get('action.admin.checkdb_title'));
        $this->SetTemplateAction('tools/checkdb');

        $sMode = $this->getParam(0, 'db');
        if ($sMode == 'blogs') {
            $this->_eventCheckDbBlogs();
        } elseif ($sMode == 'topics') {
            $this->_eventCheckDbTopics();
        }
        $this->Viewer_Assign('sMode', $sMode);
    }

    protected function _eventCheckDbBlogs() {
        $this->SetTemplateAction('tools/checkdb_blogs');
        $sDoAction = getRequest('do_action');
        if ($sDoAction == 'clear_blogs_joined') {
            $aJoinedBlogs = $this->Admin_GetUnlinkedBlogsForUsers();
            if ($aJoinedBlogs) {
                $this->Admin_DelUnlinkedBlogsForUsers(array_keys($aJoinedBlogs));
            }
        } elseif ($sDoAction == 'clear_blogs_co') {
            $aCommentsOnlineBlogs = $this->Admin_GetUnlinkedBlogsForCommentsOnline();
            if ($aCommentsOnlineBlogs) {
                $this->Admin_DelUnlinkedBlogsForCommentsOnline(array_keys($aCommentsOnlineBlogs));
            }
        }
        $aJoinedBlogs = $this->Admin_GetUnlinkedBlogsForUsers();
        $aCommentsOnlineBlogs = $this->Admin_GetUnlinkedBlogsForCommentsOnline();
        $this->Viewer_Assign('aJoinedBlogs', $aJoinedBlogs);
        $this->Viewer_Assign('aCommentsOnlineBlogs', $aCommentsOnlineBlogs);
    }

    protected function _eventCheckDbTopics() {
        $this->SetTemplateAction('tools/checkdb_topics');
        $sDoAction = getRequest('do_action');
        if ($sDoAction == 'clear_topics_co') {
            $aCommentsOnlineBlogs = $this->Admin_GetUnlinkedTopicsForCommentsOnline();
            if ($aCommentsOnlineBlogs) {
                $this->Admin_DelUnlinkedTopicsForCommentsOnline(array_keys($aCommentsOnlineBlogs));
            }
        }
        $aCommentsOnlineTopics = $this->Admin_GetUnlinkedTopicsForCommentsOnline();
        $this->Viewer_Assign('aCommentsOnlineTopics', $aCommentsOnlineTopics);
    }

    /**********************************************************************************/

    /**
     * Управление полями пользователя
     *
     */
    protected function EventUserFields() {
        switch (getRequestStr('action')) {
            /**
             * Создание нового поля
             */
            case 'add':
                /**
                 * Обрабатываем как ajax запрос (json)
                 */
                $this->Viewer_SetResponseAjax('json');
                if (!$this->checkUserField()) {
                    return;
                }
                $oField = Engine::GetEntity('User_Field');
                $oField->setName(getRequestStr('name'));
                $oField->setTitle(getRequestStr('title'));
                $oField->setPattern(getRequestStr('pattern'));
                if (in_array(getRequestStr('type'), $this->User_GetUserFieldTypes())) {
                    $oField->setType(getRequestStr('type'));
                } else {
                    $oField->setType('');
                }

                $iId = $this->User_addUserField($oField);
                if (!$iId) {
                    $this->Message_AddError($this->Lang_Get('system_error'), $this->Lang_Get('error'));
                    return;
                }
                /**
                 * Прогружаем переменные в ajax ответ
                 */
                $this->Viewer_AssignAjax('id', $iId);
                $this->Viewer_AssignAjax('lang_delete', $this->Lang_Get('user_field_delete'));
                $this->Viewer_AssignAjax('lang_edit', $this->Lang_Get('user_field_update'));
                $this->Message_AddNotice($this->Lang_Get('user_field_added'), $this->Lang_Get('attention'));
                break;
            /**
             * Удаление поля
             */
            case 'delete':
                /**
                 * Обрабатываем как ajax запрос (json)
                 */
                $this->Viewer_SetResponseAjax('json');
                if (!getRequestStr('id')) {
                    $this->Message_AddError($this->Lang_Get('system_error'), $this->Lang_Get('error'));
                    return;
                }
                $this->User_deleteUserField(getRequestStr('id'));
                $this->Message_AddNotice($this->Lang_Get('user_field_deleted'), $this->Lang_Get('attention'));
                break;
            /**
             * Изменение поля
             */
            case 'update':
                /**
                 * Обрабатываем как ajax запрос (json)
                 */
                $this->Viewer_SetResponseAjax('json');
                if (!getRequestStr('id')) {
                    $this->Message_AddError($this->Lang_Get('system_error'), $this->Lang_Get('error'));
                    return;
                }
                if (!$this->User_userFieldExistsById(getRequestStr('id'))) {
                    $this->Message_AddError($this->Lang_Get('system_error'), $this->Lang_Get('error'));
                    return false;
                }
                if (!$this->checkUserField()) {
                    return;
                }
                $oField = Engine::GetEntity('User_Field');
                $oField->setId(getRequestStr('id'));
                $oField->setName(getRequestStr('name'));
                $oField->setTitle(getRequestStr('title'));
                $oField->setPattern(getRequestStr('pattern'));
                if (in_array(getRequestStr('type'), $this->User_GetUserFieldTypes())) {
                    $oField->setType(getRequestStr('type'));
                } else {
                    $oField->setType('');
                }
                if (!$this->User_UpdateUserField($oField)) {
                    $this->Message_AddError($this->Lang_Get('system_error'), $this->Lang_Get('error'));
                    return;
                }
                $this->Message_AddNotice($this->Lang_Get('user_field_updated'), $this->Lang_Get('attention'));
                break;
            /**
             * Показываем страницу со списком полей
             */
            default:
                /**
                 * Загружаем в шаблон JS текстовки
                 */
                $this->Lang_AddLangJs(array(
                    'action.admin.user_field_delete_confirm',
                    'action.admin.user_field_admin_title_add',
                    'action.admin.user_field_admin_title_edit',
                    'action.admin.user_field_add',
                    'action.admin.user_field_update',
                ));
                /**
                 * Получаем список всех полей
                 */
                $this->Viewer_Assign('aUserFields', $this->User_getUserFields());
                $this->Viewer_Assign('aUserFieldTypes', $this->User_GetUserFieldTypes());
                $this->_setTitle($this->Lang_Get('action.admin.user_fields_title'));
                $this->SetTemplateAction('settings/userfields');
        }
    }

    /**
     * Проверка поля пользователя на корректность из реквеста
     *
     * @return bool
     */
    public function checkUserField() {
        if (!getRequestStr('title')) {
            $this->Message_AddError($this->Lang_Get('user_field_error_add_no_title'), $this->Lang_Get('error'));
            return false;
        }
        if (!getRequestStr('name')) {
            $this->Message_AddError($this->Lang_Get('user_field_error_add_no_name'), $this->Lang_Get('error'));
            return false;
        }
        /**
         * Не допускаем дубликатов по имени
         */
        if ($this->User_userFieldExistsByName(getRequestStr('name'), getRequestStr('id'))) {
            $this->Message_AddError($this->Lang_Get('user_field_error_name_exists'), $this->Lang_Get('error'));
            return false;
        }
        return true;
    }

    /**********************************************************************************/

    protected function EventContent() {
        $this->_setTitle($this->Lang_Get('action.admin.content_menu'));
        $this->SetTemplateAction('content/content');
        /*
         * Получаем список
         */
        $aFilter = array();
        $aTypes = $this->Topic_getContentTypes($aFilter, false);
        $this->Viewer_Assign('aTypes', $aTypes);

        /*
         * Выключатель
         */
        if (getRequest('toggle') && func_check(getRequest('content_id'), 'id', 1, 10) && in_array(getRequest('toggle'), array('on', 'off'))) {
            $this->Security_ValidateSendForm();
            if ($oTypeTog = $this->Topic_GetContentTypeById(getRequest('content_id'))) {
                $iToggle = 1;
                if (getRequest('toggle') == 'off') {
                    $iToggle = 0;
                }
                $oTypeTog->setContentActive($iToggle);
                $this->Topic_UpdateContentType($oTypeTog);

                Router::Location('admin/content/');
            }
        }

        if (getRequest('add')) {
            $this->Message_AddNoticeSingle($this->Lang_Get('action.admin.content_success'));
        }

        if (getRequest('edit')) {
            $this->Message_AddNoticeSingle($this->Lang_Get('action.admin.content_success_edit'));
        }


    }

    protected function EventContentAdd() {
        $this->_setTitle($this->Lang_Get('action.admin.content_add_title'));
        $this->SetTemplateAction('content/add');

        /**
         * Вызов хуков
         */
        $this->Hook_Run('topic_type_add_show');
        /**
         * Загружаем переменные в шаблон
         */
        $this->Viewer_AddHtmlTitle($this->Lang_Get('action.admin.content_add_title'));

        /**
         * Обрабатываем отправку формы
         */
        return $this->SubmitContentAdd();

    }

    protected function SubmitContentAdd() {
        /**
         * Проверяем отправлена ли форма с данными
         */
        if (!isPost('submit_type_add')) {
            return false;
        }

        /**
         * Проверка корректности полей формы
         */
        if (!$this->CheckContentFields()) {
            return false;
        }

        $oType = Engine::GetEntity('Topic_Content');
        $oType->setContentTitle(getRequest('content_title'));
        $oType->setContentTitleDecl(getRequest('content_title_decl'));
        $oType->setContentUrl(getRequest('content_url'));
        $oType->setContentCandelete('1');
        $oType->setContentAccess(getRequest('content_access'));
        $aConfig = getRequest('config');
        if (is_array($aConfig)) {
            $oType->setExtraValue('photoset', isset($aConfig['photoset']) ? 1 : 0);
            $oType->setExtraValue('link', isset($aConfig['link']) ? 1 : 0);
            $oType->setExtraValue('question', isset($aConfig['question']) ? 1 : 0);
        } else {
            $oType->setExtra('');
        }

        if ($this->Topic_AddContentType($oType)) {
            Router::Location('admin/content/?add=success');
        }


    }

    protected function EventContentEdit() {

        /**
         * Получаем тип
         */
        if (!$oType = $this->Topic_GetContentTypeById($this->GetParam(0))) {
            return parent::EventNotFound();
        }
        $this->Viewer_Assign('oType', $oType);

        /**
         * Устанавливаем шаблон вывода
         */
        $this->_setTitle($this->Lang_Get('action.admin.content_edit_title'));
        $this->SetTemplateAction('content/add');

        if (getRequest('fieldadd')) {
            $this->Message_AddNoticeSingle($this->Lang_Get('action.admin.content_success_fieldadd'));
        }
        if (getRequest('fieldedit')) {
            $this->Message_AddNoticeSingle($this->Lang_Get('action.admin.content_success_fieldedit'));
        }
        if (getRequest('fielddelete')) {
            $this->Message_AddNoticeSingle($this->Lang_Get('action.admin.content_success_fielddelete'));
        }


        /**
         * Проверяем отправлена ли форма с данными
         */
        if (isset($_REQUEST['submit_type_add'])) {
            /**
             * Обрабатываем отправку формы
             */
            return $this->SubmitContentEdit($oType);
        } else {
            $_REQUEST['content_id'] = $oType->getContentId();
            $_REQUEST['content_title'] = $oType->getContentTitle();
            $_REQUEST['content_title_decl'] = $oType->getContentTitleDecl();
            $_REQUEST['content_url'] = $oType->getContentUrl();
            $_REQUEST['content_candelete'] = $oType->getContentCandelete();
            $_REQUEST['content_access'] = $oType->getContentAccess();
            $_REQUEST['config']['photoset'] = $oType->getExtraValue('photoset');
            $_REQUEST['config']['question'] = $oType->getExtraValue('question');
            $_REQUEST['config']['link'] = $oType->getExtraValue('link');
        }

    }

    protected function SubmitContentEdit($oType) {
        /**
         * Проверяем отправлена ли форма с данными
         */
        if (!isPost('submit_type_add')) {
            return false;
        }

        /**
         * Проверка корректности полей формы
         */
        if (!$this->CheckContentFields()) {
            return false;
        }

        $sTypeOld = $oType->getContentUrl();

        $oType->setContentTitle(getRequest('content_title'));
        $oType->setContentTitleDecl(getRequest('content_title_decl'));
        $oType->setContentUrl(getRequest('content_url'));
        $oType->setContentAccess(getRequest('content_access'));
        $aConfig = getRequest('config');
        if (is_array($aConfig)) {
            $oType->setExtraValue('photoset', isset($aConfig['photoset']) ? 1 : 0);
            $oType->setExtraValue('link', isset($aConfig['link']) ? 1 : 0);
            $oType->setExtraValue('question', isset($aConfig['question']) ? 1 : 0);
        } else {
            $oType->setExtra('');
        }

        if ($this->Topic_UpdateContentType($oType)) {

            if ($oType->getContentUrl() != $sTypeOld) {

                //меняем у уже созданных топиков системный тип

                $this->Topic_changeType($sTypeOld, $oType->getContentUrl());
            }

            Router::Location('admin/content/?edit=success');
        }
    }

    public function EventAjaxChangeOrderTypes() {
        /**
         * Устанавливаем формат ответа
         */
        $this->Viewer_SetResponseAjax('json');

        if (!$this->User_IsAuthorization()) {
            $this->Message_AddErrorSingle($this->Lang_Get('need_authorization'), $this->Lang_Get('error'));
            return;
        }
        if (!$this->oUserCurrent->isAdministrator()) {
            $this->Message_AddErrorSingle($this->Lang_Get('need_authorization'), $this->Lang_Get('error'));
            return;
        }
        if (!getRequest('order')) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }


        if (is_array(getRequest('order'))) {

            foreach (getRequest('order') as $oOrder) {
                if (is_numeric($oOrder['order']) && is_numeric($oOrder['id']) && $oType = $this->Topic_GetContentTypeById($oOrder['id'])) {
                    $oType->setContentSort($oOrder['order']);
                    $this->Topic_UpdateContentType($oType);
                }
            }

            $this->Message_AddNoticeSingle($this->Lang_Get('action.admin.save_sort_success'));
            return;
        } else {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }

    }

    public function EventAjaxChangeOrderFields() {
        /**
         * Устанавливаем формат ответа
         */
        $this->Viewer_SetResponseAjax('json');

        if (!$this->User_IsAuthorization()) {
            $this->Message_AddErrorSingle($this->Lang_Get('need_authorization'), $this->Lang_Get('error'));
            return;
        }
        if (!$this->oUserCurrent->isAdministrator()) {
            $this->Message_AddErrorSingle($this->Lang_Get('need_authorization'), $this->Lang_Get('error'));
            return;
        }
        if (!getRequest('order')) {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }


        if (is_array(getRequest('order'))) {

            foreach (getRequest('order') as $oOrder) {
                if (is_numeric($oOrder['order']) && is_numeric($oOrder['id']) && $oField = $this->Topic_GetContentFieldById($oOrder['id'])) {
                    $oField->setFieldSort($oOrder['order']);
                    $this->Topic_UpdateContentField($oField);
                }
            }

            $this->Message_AddNoticeSingle($this->Lang_Get('action.admin.save_sort_success'));
            return;
        } else {
            $this->Message_AddErrorSingle($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            return;
        }

    }

    /***********************
     ****** Поля ***********
     **********************/
    protected function EventAddField() {
        $this->_setTitle($this->Lang_Get('action.admin.content_add_field_title'));

        /**
         * Получаем тип
         */
        if (!$oType = $this->Topic_GetContentTypeById($this->GetParam(0))) {
            return parent::EventNotFound();
        }

        $this->Viewer_Assign('oType', $oType);

        /**
         * Устанавливаем шаблон вывода
         */
        $this->SetTemplateAction('content/fieldadd');

        /**
         * Обрабатываем отправку формы
         */
        return $this->SubmitAddField($oType);

    }

    protected function SubmitAddField($oType) {

        /**
         * Проверяем отправлена ли форма с данными
         */
        if (!isPost('submit_field')) {
            return false;
        }

        /**
         * Проверка корректности полей формы
         */
        if (!$this->CheckFieldsField($oType)) {
            return false;
        }

        $oField = Engine::GetEntity('Topic_Field');
        $oField->setFieldType(getRequest('field_type'));
        $oField->setContentId($oType->getContentId());
        $oField->setFieldName(getRequest('field_name'));
        $oField->setFieldDescription(getRequest('field_description'));
        $oField->setFieldRequired(getRequest('field_required'));
        if (getRequest('field_type') == 'select') {
            $oField->setOptionValue('select', getRequest('field_values'));
        }

        if ($this->Topic_AddContentField($oField)) {
            Router::Location('admin/contentedit/' . $oType->getContentId() . '/?fieldadd=success');
        }


    }

    protected function EventEditField() {

        $this->Viewer_AddHtmlTitle($this->Lang_Get('action.admin.content_edit_field_title'));

        /*
         * Получаем поле
         */
        if (!$oField = $this->Topic_GetContentFieldById($this->GetParam(0))) {
            return parent::EventNotFound();
        }

        $this->Viewer_Assign('oField', $oField);

        /**
         * Получаем тип
         */
        if (!$oType = $this->Topic_GetContentTypeById($oField->getContentId())) {
            return parent::EventNotFound();
        }

        $this->Viewer_Assign('oType', $oType);

        /**
         * Устанавливаем шаблон вывода
         */
        $this->SetTemplateAction('content/fieldadd');
        /**
         * Проверяем отправлена ли форма с данными
         */
        if (isset($_REQUEST['submit_field'])) {
            /**
             * Обрабатываем отправку формы
             */
            return $this->SubmitEditField($oType, $oField);
        } else {
            $_REQUEST['field_id'] = $oField->getFieldId();
            $_REQUEST['field_type'] = $oField->getFieldType();
            $_REQUEST['field_name'] = $oField->getFieldName();
            $_REQUEST['field_description'] = $oField->getFieldDescription();
            $_REQUEST['field_required'] = $oField->getFieldRequired();
            $_REQUEST['field_values'] = $oField->getFieldValues();
        }

    }

    protected function SubmitEditField($oType, $oField) {

        /**
         * Проверяем отправлена ли форма с данными
         */
        if (!isPost('submit_field')) {
            return false;
        }

        /**
         * Проверка корректности полей формы
         */
        if (!$this->CheckFieldsField($oType)) {
            return false;
        }

        $oField->setFieldName(getRequest('field_name'));
        $oField->setFieldDescription(getRequest('field_description'));
        $oField->setFieldRequired(getRequest('field_required'));
        if ($oField->getFieldType() == 'select') {
            $oField->setOptionValue('select', getRequest('field_values'));
        }

        if ($this->Topic_UpdateContentField($oField)) {
            Router::Location('admin/contentedit/' . $oType->getContentId() . '/?fieldedit=success');
        }


    }

    protected function EventDeleteField() {
        $this->Security_ValidateSendForm();
        if (!$oField = $this->Topic_GetContentFieldById($this->GetParam(0))) {
            return parent::EventNotFound();
        }
        if (!$oType = $this->Topic_GetContentTypeById($oField->getContentId())) {
            return parent::EventNotFound();
        }

        $this->Topic_DeleteField($oField);
        Router::Location('admin/contentedit/' . $oType->getContentId() . '/?fielddelete=success');
    }


    /*************************************************************
     *
     */


    protected function CheckContentFields() {

        $this->Security_ValidateSendForm();

        $bOk = true;

        if (!func_check(getRequest('content_title', null, 'post'), 'text', 2, 200)) {
            $this->Message_AddError($this->Lang_Get('action.admin.content_type_title_error'), $this->Lang_Get('error'));
            $bOk = false;
        }

        if (!func_check(getRequest('content_title_decl', null, 'post'), 'text', 2, 200)) {
            $this->Message_AddError($this->Lang_Get('action.admin.content_type_title_decl_error'), $this->Lang_Get('error'));
            $bOk = false;
        }
        if (!func_check(getRequest('content_url', null, 'post'), 'login', 2, 50) || in_array(getRequest('content_url', null, 'post'), array_keys(Config::Get('router.page')))) {
            $this->Message_AddError($this->Lang_Get('action.admin.content_type_url_error'), $this->Lang_Get('error'));
            $bOk = false;
        }

        if (!in_array(getRequest('content_access'), array('1', '2', '4'))) {
            $this->Message_AddError($this->Lang_Get('system_error'), $this->Lang_Get('error'));
            $bOk = false;
        }

        return $bOk;
    }

    protected function CheckFieldsField($oType = null) {

        $this->Security_ValidateSendForm();

        $bOk = true;

        if (!func_check(getRequest('field_name', null, 'post'), 'text', 2, 100)) {
            $this->Message_AddError($this->Lang_Get('field_name_error'), $this->Lang_Get('error'));
            $bOk = false;
        }

        if (!func_check(getRequest('field_description', null, 'post'), 'text', 2, 200)) {
            $this->Message_AddError($this->Lang_Get('field_description_error'), $this->Lang_Get('error'));
            $bOk = false;
        }

        if (Router::GetActionEvent() == 'fieldadd') {
            if ($oType == 'photoset' && (getRequest('field_type', null, 'post') == 'photoset' || $oType->isPhotosetEnable())) {
                $this->Message_AddError($this->Lang_Get('system_error'), $this->Lang_Get('error'));
                $bOk = false;
            }

            if ($oType == 'litepoll' && (getRequest('field_type', null, 'post') == 'litepoll' || $oType->isLitepollEnable())) {
                $this->Message_AddError($this->Lang_Get('system_error'), $this->Lang_Get('error'));
                $bOk = false;
            }

            if (!in_array(getRequest('field_type', null, 'post'), array('input', 'textarea', 'photoset', 'link', 'select', 'date', 'map', 'daoobj', 'object', 'user', 'file', 'litepoll', 'gallery'))) {
                $this->Message_AddError($this->Lang_Get('field_type_error'), $this->Lang_Get('error'));
                $bOk = false;
            }
        }

        /*
        * Проверяем валидность связи, если поле - связь с разделом DAO
        */
        if (getRequest('field_type', null, 'post') == 'daoobj') {
            if (!in_array(getRequest('link_display_type', null, 'post'), array('multiple', 'once')) ||
                !in_array(getRequest('link_access', null, 'post'), array('self', 'all')) ||
                !in_array(getRequest('view_type', null, 'post'), array('link', 'obj')) ||
                !$oCatalog = $this->PluginDao_Catalog_getCatalogTypeByCatalogId(getRequest('catalog_id'))

            ) {
                $this->Message_AddError($this->Lang_Get('field_daoobj_error'), $this->Lang_Get('error'));
                $bOk = false;
            }
        }

        return $bOk;
    }

    /**
     * Выполняется при завершении работы экшена
     *
     */
    public function EventShutdown() {
        /**
         * Загружаем в шаблон необходимые переменные
         */
        $this->Viewer_Assign('sMenuItem', $this->sMenuItem);
    }

    protected function _setTitle($sTitle) {
        $this->Viewer_Assign('sPageTitle', $sTitle);
        $this->Viewer_AddHtmlTitle($sTitle);

    }

    protected function _getPageNum($nNumParam = null) {
        $nPage = 1;
        if (!is_null($nNumParam) && preg_match("/^page(\d+)$/i", $this->GetParam(intval($nNumParam)), $aMatch)) {
            $nPage = $aMatch[1];
        } elseif (preg_match("/^page(\d+)$/i", $this->GetLastParam(), $aMatch)) {
            $nPage = $aMatch[1];
        }
        return $nPage;
    }


}

// EOF