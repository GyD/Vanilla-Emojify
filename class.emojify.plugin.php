<?php if (!defined('APPLICATION')) {
    exit();
}
/*
  Copyright 2008, 2009 Vanilla Forums Inc.
  This file is part of Garden.
  Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
  Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
  You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
  Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
 */

$PluginInfo['Emojify'] = array(
  'Description' => 'Allow users to use emoji in Vanilla Forums',
  'Version' => '1.0.1',
  'RequiredApplications' => array('Vanilla' => '2.1.8p2'),
  'RequiredTheme' => false,
  'RequiredPlugins' => false,
  'SettingsUrl' => 'dashboard/settings/emojify',
  'HasLocale' => false,
  'Author' => "GyD",
  'AuthorEmail' => 'contact@gyd.be',
  'AuthorUrl' => 'https://github.com/GyD'
);

/**
 * Class emojify
 */
class EmojifyPlugin extends Gdn_Plugin
{

    /**
     * Default value for canParse setting
     */
    const CANPARSE = true;
    /**
     * Default value for parseTitle setting
     */
    const PARSETITLE = true;

    /**
     * Contain emojify Catalog
     * @var
     */
    private $catalog;

    /**
     * Contain parsing status
     * @var
     */
    private $parsed = false;

    /**
     * At Libraries Startup
     * @param LibrariesPlugin $Sender
     */
    public function LibrariesPlugin_Startup_Handler($Sender)
    {
        $Sender->addLibraries(array(
          'jquery-textcomplete' => array(
            'version' => '0.3.9',
            'plugin' => get_class($this),
            'files' => array(
              'js' => "js/jquery-textcomplete/dist/jquery.textcomplete.js",
              'js-min' => "js/jquery-textcomplete/dist/jquery.textcomplete.min.js",
              'css' => "js/jquery-textcomplete/dist/jquery.textcomplete.css",
                //'css-min' => "js/jquery-textcomplete/dist/jquery.textcomplete.css",
            ),
          ),
        ));
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param $Sender
     */
    public function DiscussionController_Render_Before($Sender)
    {
        LibrariesPlugin::AttachLibrary($Sender, 'jquery-textcomplete');
        $Sender->AddJsFile($this->GetResource('js/emojify.js', false, false));
    }

    /**
     * @param $Sender
     */
    public function DiscussionModel_BeforeSaveDiscussion_Handler($Sender)
    {
        $this->RemoveEmojiBeforeSave($Sender);
    }

    /**
     * Replace the emojies into short code before save
     *
     * @param $Sender
     */
    public function CommentModel_BeforeSaveComment_Handler($Sender)
    {
        $this->RemoveEmojiBeforeSave($Sender);
    }

    /**
     * @param $Sender
     */
    public function DiscussionModel_BeforeSaveDraft_Handler($Sender)
    {
        $this->RemoveEmojiBeforeSave($Sender);
    }

    /**
     * Remove Emoji Before Save (For Comments, Discussions [and Draft])
     * @param $Sender
     */
    private function RemoveEmojiBeforeSave(&$Sender)
    {
        // remove emojies from body
        $body = GetValueR('EventArguments.FormPostValues.Body', $Sender);
        if ($body) {
            $this->emojiToShort($body);
            SetValue('Body', $Sender->EventArguments['FormPostValues'], $body);
        }

        // remove emojies from title
        $name = GetValueR('EventArguments.FormPostValues.Name', $Sender);
        if ($name) {
            $this->emojiToShort($name);
            SetValue('Name', $Sender->EventArguments['FormPostValues'], $name);
        }
    }

    /**
     * Convert emoji to short code
     *
     * @param $string
     * @param string $catalog
     */
    private function emojiToShort(&$string, $catalog = 'unified')
    {
        $string = str_ireplace(array_keys($this->getCatalog()['toShort'][$catalog]), $this->getCatalog()['toShort'][$catalog], $string);
    }

    /**
     * Get the emojify catalog
     *
     * @return array
     */
    private function getCatalog()
    {
        if (null == $this->catalog) {
            $catalog = array();
            require_once __DIR__ . '/catalog.php';
            $this->catalog = $catalog;
        }

        return $this->catalog;

    }

    /**
     * Replace emojify short code in comments.
     *
     * @param $Sender
     */
    public function DiscussionController_BeforeCommentBody_Handler($Sender)
    {
        $this->setParsed(false);
    }

    /**
     * @param $Sender
     */
    public function ParsedownPlugin_BeforeFormat_Handler($Sender)
    {
        if ($this->canParse() && !$this->isParsed()) {
            $this->shortToEmoji($Sender->EventArguments['Result']);
            $this->setParsed(true);
        }
    }

    /**
     * Alter Discussion Title on Discussion List
     * @param $Sender
     */
    public function DiscussionsController_BeforeDiscussionName_Handler($Sender)
    {
        if (C('Plugins.Emojify.ParseTitle', self::PARSETITLE) && $this->canParse()) {
            $name = GetValueR('EventArguments.Discussion.Name', $Sender);
            if ($name) {
                $this->shortToEmoji($name);
                $Sender->EventArguments['Discussion']->Name = $name;
            }
        }
    }

    /**
     * Alter title on discussion page to transform colo emoji to emojies
     *
     * @param $Sender
     */
    public function DiscussionController_BeforeDiscussionOptions_Handler($Sender)
    {
        if (C('Plugins.Emojify.ParseTitle', self::PARSETITLE) && $this->canParse()) {
            $name = $Sender->Data('Discussion.Name');
            if ($name) {
                $this->shortToEmoji($name);
                $Sender->Data['Discussion']->Name = $name;
            }
        }
    }

    /**
     * Short code to Emoji
     *
     * @param $string
     */
    private function shortToEmoji(&$string)
    {
        $test = $this->getCatalog()['toHtml'];
        $string = str_ireplace(array_keys($test), $test, $string);
    }

    /**
     * Replace emojify short code in comments.
     *
     * @param $Sender
     */
    public function Base_AfterCommentFormat_Handler($Sender)
    {
        if ($this->canParse() && !$this->isParsed()) {
            $modelType = GetValueR('EventArguments.Type', $Sender);
            $formatBody = GetValueR('EventArguments.' . $modelType . '.FormatBody', $Sender);
            if ($formatBody) {
                $this->shortToEmoji($formatBody);
                $Sender->EventArguments[$modelType]->FormatBody = $formatBody;
            }

            $this->setParsed(true);
        }
    }

    /**
     * Is body parsed
     *
     * @return bool
     */
    private function isParsed(){
        return $this->parsed;
    }

    /**
     * Set body as parsed
     *
     * @param bool $parsed
     */
    private function setParsed($parsed = true){
        $this->parsed = $parsed;
    }

    /**
     * Can we parse the string ?
     *
     * @return bool
     */
    private function canParse()
    {
        if (!C('Plugins.Emojify.CanParse', self::CANPARSE)) {
            return false;
        }

        return true;
    }

    /**
     * Convert Emoji to short code in preview
     *
     * @param $Sender
     */
    public function Base_AfterCommentPreviewFormat_Handler($Sender)
    {
        if ($this->canParse() && !$this->isParsed()) {
            $this->shortToEmoji($Sender->Comment->Body);
            $this->setParsed(true);
        }
    }

    /**
     * Convert Emoji to short code in preview
     *
     * @param $Sender
     */
    public function Base_BeforeCommentPreviewFormat_Handler($Sender)
    {
        $this->emojiToShort($Sender->Comment->Body);
    }

    /**
     * @param $Sender
     */
    public function SettingsController_Emojify_Create($Sender)
    {
        $Sender->Permission('Garden.Settings.Manage');
        $Sender->SetData('Title', T('Emojify Settings'));
        $Sender->AddSideMenu('dashboard/settings/plugins');

        $Conf = new ConfigurationModule($Sender);
        $Conf->Initialize(array(
          'Plugins.Emojify.CanParse' => array(
            'LabelCode' => 'Transform short code into emoji',
            'Control' => 'Checkbox',
            'Default' => self::CANPARSE,
          ),
          'Plugins.Emojify.ParseTitle' => array(
            'LabelCode' => 'Also Transform short code into emoji into Discussion Names (titles)',
            'Control' => 'Checkbox',
            'Default' => self::PARSETITLE,
          ),
        ));
        $Conf->RenderAll();
    }
}