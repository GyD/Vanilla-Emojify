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
  'Version' => '1.0.0',
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
class emojify extends Gdn_Plugin
{

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
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }


    public function DiscussionController_Render_Before($Sender)
    {
        $Sender->AddJsFile($this->GetResource('emojify.js', false, false));
    }

    /**
     * @param $Sender
     */
    public function Base_BeforeSaveDiscussion_Handler($Sender)
    {
        $this->emojiToShort($Sender->EventArguments['FormPostValues']['Body']);

        return;
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
     * Replace the emojies into short code before save
     *
     * @param $Sender
     */
    public function Base_BeforeSaveComment_Handler($Sender)
    {
        $this->emojiToShort($Sender->EventArguments['FormPostValues']['Body']);
    }

    /**
     * Replace emojify short code in comments.
     * 
     * @param $Sender
     */
    public function Base_BeforeCommentBody_Handler($Sender)
    {
        $this->parsed = false;
    }

    /**
     * Replace emojify short code in comments.
     *
     * @param $Sender
     */
    public function Base_AfterCommentFormat_Handler($Sender)
    {
        if ($this->canParse()) {
            $this->shortToEmoji($Sender->EventArguments[$Sender->EventArguments['Type']]->FormatBody);
        }
    }

    /**
     * Can we parse the string ?
     *
     * @return bool
     */
    private function canParse()
    {
        if (!$this->parsed) {
            $this->parsed = true;

            return true;
        }

        return false;
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
     * Convert Emoji to short code in preview
     *
     * @param $Sender
     */
    public function Base_AfterCommentPreviewFormat_Handler($Sender)
    {
        if ($this->canParse()) {
            $this->shortToEmoji($Sender->Comment->Body);
        }
    }

    /**
     * Convert Emoji to short code in preview
     *
     * @param $Sender
     */
    public function Base_CommentPreviewFormat_Handler($Sender)
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
            'Default' => true,
          ),
        ));
        $Conf->RenderAll();
    }
}