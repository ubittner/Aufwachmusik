<?php

/**
 * @project       Aufwachmusik/Aufwachmusik
 * @file          module.php
 * @author        Ulrich Bittner
 * @copyright     2023 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnused */

declare(strict_types=1);

include_once __DIR__ . '/helper/autoload.php';

class Aufwachmusik extends IPSModule
{
    //Helper
    use Control;
    use Presets;
    use WeeklySchedule;

    //Constants
    private const MODULE_PREFIX = 'AWM';

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        ##### Properties

        //Device
        $this->RegisterPropertyInteger('DevicePower', 0);
        $this->RegisterPropertyInteger('DeviceVolume', 0);
        $this->RegisterPropertyInteger('DevicePresets', 0);

        //Weekly schedule
        $this->RegisterPropertyInteger('WeeklySchedule', 0);

        ##### Variables

        //Wake up music
        $this->RegisterVariableBoolean('WakeUpMusic', 'Aufwachmusik', '~Switch', 10);
        $this->EnableAction('WakeUpMusic');

        //Volume
        $id = @$this->GetIDForIdent('Volume');
        $this->RegisterVariableInteger('Volume', 'Lautstärke', '~Volume', 20);
        $this->EnableAction('Volume');
        if (!$id) {
            $this->SetValue('Volume', 15);
        }

        //Presets
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.Presets';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, 'Menu');
        IPS_SetVariableProfileValues($profile, 0, 6, 0);
        IPS_SetVariableProfileDigits($profile, 0);
        IPS_SetVariableProfileAssociation($profile, 0, 'Zuletzt wiedergegeben', '', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 1, 'Preset 1', '', 0x0000FF);
        IPS_SetVariableProfileAssociation($profile, 2, 'Preset 2', '', 0x0000FF);
        IPS_SetVariableProfileAssociation($profile, 3, 'Preset 3', '', 0x0000FF);
        IPS_SetVariableProfileAssociation($profile, 4, 'Preset 4', '', 0x0000FF);
        IPS_SetVariableProfileAssociation($profile, 5, 'Preset 5', '', 0x0000FF);
        IPS_SetVariableProfileAssociation($profile, 6, 'Preset 6', '', 0x0000FF);
        $id = @$this->GetIDForIdent('Presets');
        $this->RegisterVariableInteger('Presets', 'Presets', $profile, 30);
        $this->EnableAction('Presets');
        if (!$id) {
            $this->SetValue('Presets', 0);
        }

        //Duration
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.Duration';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, 'Hourglass');
        IPS_SetVariableProfileValues($profile, 15, 120, 0);
        IPS_SetVariableProfileDigits($profile, 0);
        IPS_SetVariableProfileAssociation($profile, 15, '15 Min.', '', 0x0000FF);
        IPS_SetVariableProfileAssociation($profile, 30, '30 Min.', '', 0x0000FF);
        IPS_SetVariableProfileAssociation($profile, 45, '45 Min.', '', 0x0000FF);
        IPS_SetVariableProfileAssociation($profile, 60, '60 Min.', '', 0x0000FF);
        IPS_SetVariableProfileAssociation($profile, 90, '90 Min.', '', 0x0000FF);
        IPS_SetVariableProfileAssociation($profile, 120, '120 Min.', '', 0x0000FF);
        $id = @$this->GetIDForIdent('Duration');
        $this->RegisterVariableInteger('Duration', 'Dauer', $profile, 40);
        $this->EnableAction('Duration');
        if (!$id) {
            $this->SetValue('Duration', 30);
        }

        //Process finished
        $id = @$this->GetIDForIdent('ProcessFinished');
        $this->RegisterVariableString('ProcessFinished', 'Schaltvorgang bis', '', 60);
        if (!$id) {
            IPS_SetIcon($this->GetIDForIdent('ProcessFinished'), 'Clock');
        }

        ##### Attributes

        $this->RegisterAttributeInteger('TargetVolume', 0);
        $this->RegisterAttributeInteger('CyclingVolume', 0);
        $this->RegisterAttributeInteger('EndTime', 0);

        #### Timer

        $this->RegisterTimer('IncreaseVolume', 0, self::MODULE_PREFIX . '_IncreaseVolume(' . $this->InstanceID . ');');
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();

        //Delete profiles
        $profiles = ['Presets', 'Duration'];
        if (!empty($profiles)) {
            foreach ($profiles as $profile) {
                $profileName = self::MODULE_PREFIX . '.' . $this->InstanceID . '.' . $profile;
                $this->UnregisterProfile($profileName);
            }
        }
    }

    public function ApplyChanges()
    {
        //Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        //Never delete this line!
        parent::ApplyChanges();

        //Check kernel runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        //Delete all references
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }

        //Delete all messages
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                if ($message == VM_UPDATE || $message == EM_UPDATE) {
                    $this->UnregisterMessage($senderID, $message);
                }
            }
        }

        //Register references and messages
        $names = [];
        $names[] = ['propertyName' => 'DevicePower', 'messageCategory' => VM_UPDATE];
        $names[] = ['propertyName' => 'DeviceVolume', 'messageCategory' => VM_UPDATE];
        $names[] = ['propertyName' => 'DevicePresets', 'messageCategory' => 0];
        $names[] = ['propertyName' => 'WeeklySchedule', 'messageCategory' => EM_UPDATE];
        foreach ($names as $name) {
            $id = $this->ReadPropertyInteger($name['propertyName']);
            if ($id > 1 && @IPS_ObjectExists($id)) { //0 = main category, 1 = none
                $this->RegisterReference($id);
                if ($name['messageCategory'] != 0) {
                    $this->RegisterMessage($id, $name['messageCategory']);
                }
            }
        }

        //Check presets
        if (!$this->CheckDevicePresetsID()) {
            $hideMode = true;
        } else {
            $hideMode = false;
        }
        @IPS_SetHidden($this->GetIDForIdent('Presets'), $hideMode);

        //Hide process finished
        if (!$this->GetValue('WakeUpMusic')) {
            @IPS_SetHidden($this->GetIDForIdent('ProcessFinished'), true);
        }

        //Update presets
        $this->UpdatePresetsProfile();

        //Check weekly schedule
        if (!$this->ValidateWeeklySchedule()) {
            $this->DeleteWeeklySchedule();
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            case VM_UPDATE:

                //$Data[0] = actual value
                //$Data[1] = value changed
                //$Data[2] = last value
                //$Data[3] = timestamp actual value
                //$Data[4] = timestamp value changed
                //$Data[5] = timestamp last value

                //Device power
                $devicePowerID = $this->ReadPropertyInteger('DevicePower');
                if ($SenderID == $devicePowerID) {
                    //Device is powered off
                    if ($this->GetValue('WakeUpMusic') && !GetValue($devicePowerID)) {
                        $this->SendDebug(__FUNCTION__, 'Abbruch, Gerät wurde ausgeschaltet!', 0);
                        $this->ToggleWakeUpMusic(false);
                    }
                }

                //Device volume
                $deviceVolumeID = $this->ReadPropertyInteger('DeviceVolume');
                if ($SenderID == $deviceVolumeID) {
                    if ($this->GetValue('WakeUpMusic')) {
                        $this->SendDebug(__FUNCTION__, 'Geräte-Lautstärke: ' . $Data[0], 0);
                        $deviceVolume = GetValue($deviceVolumeID);
                        $cyclingVolume = $this->ReadAttributeInteger('CyclingVolume');
                        if ($deviceVolume != $cyclingVolume) {
                            $this->SendDebug(__FUNCTION__, 'Abbruch, Geräte-Lautstärke wurde manuell geändert!', 0);
                            $this->ToggleWakeUpMusic(false);
                        }
                    }
                }
                break;

            case EM_UPDATE:

                //$Data[0] = last run
                //$Data[1] = next run

                //Weekly schedule
                if ($this->ValidateWeeklySchedule()) {
                    if ($this->DetermineAction() == 1) {
                        $this->ToggleWakeUpMusic(true);
                    }
                }
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $data = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        ##### Elements

        //Device power
        $devicePowerID = $this->ReadPropertyInteger('DevicePower');
        $enableButton = false;
        if ($devicePowerID > 1 && @IPS_ObjectExists($devicePowerID)) { //0 = main category, 1 = none
            $enableButton = true;
        }
        $data['elements'][0]['items'][1] = [
            'type'     => 'OpenObjectButton',
            'caption'  => 'ID ' . $devicePowerID . ' bearbeiten',
            'name'     => 'DevicePowerConfigurationButton',
            'visible'  => $enableButton,
            'objectID' => $devicePowerID
        ];

        //Device volume
        $deviceVolumeID = $this->ReadPropertyInteger('DeviceVolume');
        $enableButton = false;
        if ($deviceVolumeID > 1 && @IPS_ObjectExists($deviceVolumeID)) { //0 = main category, 1 = none
            $enableButton = true;
        }
        $data['elements'][1]['items'][1] = [
            'type'     => 'OpenObjectButton',
            'caption'  => 'ID ' . $deviceVolumeID . ' bearbeiten',
            'name'     => 'DeviceVolumeConfigurationButton',
            'visible'  => $enableButton,
            'objectID' => $deviceVolumeID
        ];

        //Device presets
        $devicePresetsID = $this->ReadPropertyInteger('DevicePresets');
        $enableButton = false;
        if ($devicePresetsID > 1 && @IPS_ObjectExists($devicePresetsID)) { //0 = main category, 1 = none
            $enableButton = true;
        }
        $data['elements'][2]['items'][1] = [
            'type'     => 'OpenObjectButton',
            'caption'  => 'ID ' . $devicePresetsID . ' bearbeiten',
            'name'     => 'DevicePresetsConfigurationButton',
            'visible'  => $enableButton,
            'objectID' => $devicePresetsID
        ];

        $data['elements'][2]['items'][2] = [
            'type'    => 'Button',
            'caption' => 'Presets aktualisieren',
            'name'    => 'UpdatePresetsConfigurationButton',
            'visible' => $enableButton,
            'onClick' => 'AWM_UpdatePresetsProfile($id);'
        ];

        //Weekly schedule
        $weeklyScheduleID = $this->ReadPropertyInteger('WeeklySchedule');
        $enableButton = false;
        if ($weeklyScheduleID > 1 && @IPS_ObjectExists($weeklyScheduleID)) { //0 = main category, 1 = none
            $enableButton = true;
        }
        $data['elements'][4]['items'][1] = [
            'type'     => 'OpenObjectButton',
            'caption'  => 'ID ' . $weeklyScheduleID . ' bearbeiten',
            'name'     => 'WeeklyScheduleConfigurationButton',
            'visible'  => $enableButton,
            'objectID' => $weeklyScheduleID

        ];

        //Create weekly schedule button
        $data['elements'][5] = [
            'type'    => 'PopupButton',
            'caption' => 'Wochenplan erstellen',
            'popup'   => [
                'caption' => 'Wochenplan wirklich erstellen und zuweisen?',
                'items'   => [
                    [
                        'type'  => 'RowLayout',
                        'items' => [
                            [
                                'type'  => 'CheckBox',
                                'name'  => 'UseMonday',
                                'value' => true
                            ],
                            [
                                'type'    => 'Label',
                                'caption' => "Montag\t\t"
                            ],
                            [
                                'type'    => 'SelectTime',
                                'name'    => 'MondayStartTime',
                                'caption' => 'Startzeit',
                                'width'   => '120px',
                                'value'   => '{"hour": "06", "minute": "00", "second": "00"}'
                            ]
                        ]
                    ],
                    [
                        'type'  => 'RowLayout',
                        'items' => [
                            [
                                'type'  => 'CheckBox',
                                'name'  => 'UseTuesday',
                                'value' => true
                            ],
                            [
                                'type'    => 'Label',
                                'caption' => "Dienstag\t"
                            ],
                            [
                                'type'    => 'SelectTime',
                                'name'    => 'TuesdayStartTime',
                                'caption' => 'Startzeit',
                                'width'   => '120px',
                                'value'   => '{"hour": "06", "minute": "00", "second": "00"}'
                            ]
                        ]
                    ],
                    [
                        'type'  => 'RowLayout',
                        'items' => [
                            [
                                'type'  => 'CheckBox',
                                'name'  => 'UseWednesday',
                                'value' => true
                            ],
                            [
                                'type'    => 'Label',
                                'caption' => "Mittwoch\t"
                            ],
                            [
                                'type'    => 'SelectTime',
                                'name'    => 'WednesdayStartTime',
                                'caption' => 'Startzeit',
                                'width'   => '120px',
                                'value'   => '{"hour": "06", "minute": "00", "second": "00"}'
                            ]
                        ]
                    ],
                    [
                        'type'  => 'RowLayout',
                        'items' => [
                            [
                                'type'  => 'CheckBox',
                                'name'  => 'UseThursday',
                                'value' => true
                            ],
                            [
                                'type'    => 'Label',
                                'caption' => "Donnerstag\t"
                            ],
                            [
                                'type'    => 'SelectTime',
                                'name'    => 'ThursdayStartTime',
                                'caption' => 'Startzeit',
                                'width'   => '120px',
                                'value'   => '{"hour": "06", "minute": "00", "second": "00"}'
                            ]
                        ]
                    ],
                    [
                        'type'  => 'RowLayout',
                        'items' => [
                            [
                                'type'  => 'CheckBox',
                                'name'  => 'UseFriday',
                                'value' => true
                            ],
                            [
                                'type'    => 'Label',
                                'caption' => "Freitag\t\t"
                            ],
                            [
                                'type'    => 'SelectTime',
                                'name'    => 'FridayStartTime',
                                'caption' => 'Startzeit',
                                'width'   => '120px',
                                'value'   => '{"hour": "06", "minute": "00", "second": "00"}'
                            ]
                        ]
                    ],
                    [
                        'type'  => 'RowLayout',
                        'items' => [
                            [
                                'type'  => 'CheckBox',
                                'name'  => 'UseSaturday',
                                'value' => true
                            ],
                            [
                                'type'    => 'Label',
                                'caption' => "Samstag\t"
                            ],
                            [
                                'type'    => 'SelectTime',
                                'name'    => 'SaturdayStartTime',
                                'caption' => 'Startzeit',
                                'width'   => '120px',
                                'value'   => '{"hour": "07", "minute": "30", "second": "00"}'
                            ]
                        ]
                    ],
                    [
                        'type'  => 'RowLayout',
                        'items' => [
                            [
                                'type'  => 'CheckBox',
                                'name'  => 'UseSunday',
                                'value' => true
                            ],
                            [
                                'type'    => 'Label',
                                'caption' => "Sonntag\t\t"
                            ],
                            [
                                'type'    => 'SelectTime',
                                'name'    => 'SundayStartTime',
                                'caption' => 'Startzeit',
                                'width'   => '120px',
                                'value'   => '{"hour": "08", "minute": "30", "second": "00"}'
                            ]
                        ]
                    ],
                    [
                        'type'    => 'Button',
                        'caption' => 'Erstellen',
                        'onClick' => [
                            '$events["Monday"] = ["days" => 1, "use" => $UseMonday, "startTime" => $MondayStartTime];',
                            '$events["Tuesday"] = ["days" => 2, "use" => $UseTuesday, "startTime" => $TuesdayStartTime];',
                            '$events["Wednesday"] = ["days" => 4, "use" => $UseWednesday, "startTime" => $WednesdayStartTime];',
                            '$events["Thursday"] = ["days" => 8, "use" => $UseThursday, "startTime" => $ThursdayStartTime];',
                            '$events["Friday"] = ["days" => 16, "use" => $UseFriday, "startTime" => $FridayStartTime];',
                            '$events["Saturday"] = ["days" => 32, "use" => $UseSaturday, "startTime" => $SaturdayStartTime];',
                            '$events["Sunday"] = ["days" => 64, "use" => $UseSunday, "startTime" => $SundayStartTime];',
                            '$eventID = AWM_CreateWeeklySchedule($id, json_encode($events));'
                        ]
                    ]
                ]
            ]
        ];

        return json_encode($data);
    }

    /**
     * Modifies a configuration button.
     *
     * @param string $Field
     * @param string $Caption
     * @param int $ObjectID
     * @return void
     */
    public function ModifyButton(string $Field, string $Caption, int $ObjectID): void
    {
        $state = false;
        if ($ObjectID > 1 && @IPS_ObjectExists($ObjectID)) { //0 = main category, 1 = none
            $state = true;
        }
        $this->UpdateFormField($Field, 'caption', $Caption);
        $this->UpdateFormField($Field, 'visible', $state);
        $this->UpdateFormField($Field, 'objectID', $ObjectID);
    }

    #################### Request action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'WakeUpMusic':
                $this->ToggleWakeUpMusic($Value);
                break;

            case 'Volume':
            case 'Presets':
            case 'Duration':
                $this->SetValue($Ident, $Value);
                break;

        }
    }

    #################### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    /**
     * Unregisters a variable profile.
     *
     * @param string $Name
     * @return void
     */
    private function UnregisterProfile(string $Name): void
    {
        if (!IPS_VariableProfileExists($Name)) {
            return;
        }
        foreach (IPS_GetVariableList() as $VarID) {
            if (IPS_GetParent($VarID) == $this->InstanceID) {
                continue;
            }
            if (IPS_GetVariable($VarID)['VariableCustomProfile'] == $Name) {
                return;
            }
            if (IPS_GetVariable($VarID)['VariableProfile'] == $Name) {
                return;
            }
        }
        foreach (IPS_GetMediaListByType(MEDIATYPE_CHART) as $mediaID) {
            $content = json_decode(base64_decode(IPS_GetMediaContent($mediaID)), true);
            foreach ($content['axes'] as $axis) {
                if ($axis['profile' === $Name]) {
                    return;
                }
            }
        }
        IPS_DeleteVariableProfile($Name);
    }
}