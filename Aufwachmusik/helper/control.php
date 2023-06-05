<?php

/**
 * @project       Aufwachmusik/Aufwachmusik/helper
 * @file          control.php
 * @author        Ulrich Bittner
 * @copyright     2023 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpVoidFunctionResultUsedInspection */

declare(strict_types=1);

trait Control
{
    /**
     * Toggles the wake-up music off or on.
     *
     * @param bool $State
     * false =  off,
     * true =   on
     *
     * @return bool
     * false =  an error occurred,
     * true =   successful
     *
     * @throws Exception
     */
    public function ToggleWakeUpMusic(bool $State): bool
    {
        $debugText = 'Aufwachmusik ausschalten';
        if ($State) {
            $debugText = 'Aufwachmusik einschalten';
        }
        $this->SendDebug(__FUNCTION__, $debugText, 0);
        //Reset timer
        $this->SetTimerInterval('IncreaseVolume', 0);
        $this->SetTimerInterval('AutomaticPowerOff', 0);
        //Off
        if (!$State) {
            $this->SetValue('WakeUpMusic', false);
            IPS_SetDisabled($this->GetIDForIdent('Volume'), false);
            IPS_SetDisabled($this->GetIDForIdent('Presets'), false);
            IPS_SetDisabled($this->GetIDForIdent('Duration'), false);
            IPS_SetDisabled($this->GetIDForIdent('AutomaticPowerOff'), false);
            $this->SetValue('ProcessFinished', '');
            @IPS_SetHidden($this->GetIDForIdent('ProcessFinished'), true);
            $this->WriteAttributeInteger('TargetVolume', 0);
            $this->WriteAttributeInteger('CyclingVolume', 0);
            $this->WriteAttributeInteger('EndTime', 0);
        } //On
        else {
            if (!$this->CheckDevicePowerID()) {
                return false;
            }
            if (!$this->CheckDeviceVolumeID()) {
                return false;
            }
            //Check if device is already powered on
            if (GetValue($this->ReadPropertyInteger('DevicePower'))) {
                $this->SendDebug(__FUNCTION__, 'Abbruch, Gerät ist bereits eingeschaltet!', 0);
                return false;
            }
            //Timestamp
            $timestamp = time() + $this->GetValue('Duration') * 60;
            //Set values
            $this->SetValue('WakeUpMusic', true);
            IPS_SetDisabled($this->GetIDForIdent('Volume'), true);
            IPS_SetDisabled($this->GetIDForIdent('Presets'), true);
            IPS_SetDisabled($this->GetIDForIdent('Duration'), true);
            $this->SetValue('ProcessFinished', date('d.m.Y, H:i:s', $timestamp));
            @IPS_SetHidden($this->GetIDForIdent('ProcessFinished'), false);
            //Set attributes
            $targetVolume = $this->GetValue('Volume');
            $this->WriteAttributeInteger('TargetVolume', $targetVolume);
            $this->SendDebug(__FUNCTION__, 'Ziellautstärke: ' . $targetVolume, 0);
            $volume = 1;
            $this->WriteAttributeInteger('CyclingVolume', $volume);
            $this->SendDebug(__FUNCTION__, 'Lautstärke: ' . $volume, 0);
            $this->WriteAttributeInteger('EndTime', $timestamp);
            //Set device volume
            $this->SetDeviceVolume($volume);
            //Set device preset
            $powerOn = true;
            if ($this->CheckDevicePresetsID()) {
                $devicePresetID = $this->ReadPropertyInteger('DevicePresets');
                $preset = $this->GetValue('Presets');
                if ($preset > 0) {
                    $powerOn = false;
                    $setDevicePreset = @RequestAction($devicePresetID, $preset);
                    $this->SendDebug(__FUNCTION__, 'Preset: ' . $preset, 0);
                    //Try again
                    if (!$setDevicePreset) {
                        @RequestAction($devicePresetID, $preset);
                    }
                }
            }
            //Power device on
            if ($powerOn) {
                $this->SendDebug(__FUNCTION__, 'Gerät einschalten', 0);
                $this->PowerDevice(true);
            }
            //Set next cycle
            $this->SetTimerInterval('IncreaseVolume', $this->CalculateNextCycle() * 1000);
        }
        return true;
    }

    /**
     * Increases the volume of the device.
     *
     * @return void
     * @throws Exception
     */
    public function IncreaseVolume(): void
    {
        if (!$this->CheckDevicePowerID()) {
            return;
        }
        if (!$this->CheckDeviceVolumeID()) {
            return;
        }
        //cycling volume
        $cyclingVolume = $this->ReadAttributeInteger('CyclingVolume');
        $cyclingVolume++;
        $this->SendDebug(__FUNCTION__, 'Lautstärke: ' . $cyclingVolume, 0);
        $this->WriteAttributeInteger('CyclingVolume', $cyclingVolume);
        //Target volume
        $targetVolume = $this->ReadAttributeInteger('TargetVolume');
        //Set device volume
        $this->SetDeviceVolume($cyclingVolume);
        //Check for last cycle
        if ($cyclingVolume == $targetVolume) {
            //Check automatic power off
            $automaticPowerOff = $this->GetValue('AutomaticPowerOff');
            if ($automaticPowerOff > 0) {
                $this->SendDebug(__FUNCTION__, 'Ausschalten: ' . $this->GetValue('AutomaticPowerOff') . ' Minuten', 0);
                IPS_SetDisabled($this->GetIDForIdent('AutomaticPowerOff'), true);
                $timestamp = time() + $this->GetValue('AutomaticPowerOff') * 60;
                $this->SetValue('ProcessFinished', date('d.m.Y, H:i:s', $timestamp));
                $this->SetTimerInterval('IncreaseVolume', 0);
                $this->SetTimerInterval('AutomaticPowerOff', $automaticPowerOff * 60 * 1000);
            } else {
                $this->ToggleWakeUpMusic(false);
            }
            return;
        }
        //Set next cycle
        $this->SetTimerInterval('IncreaseVolume', $this->CalculateNextCycle() * 1000);
    }

    /**
     * Powers the device off or on.
     *
     * @param bool $State
     * false =  off,
     * true =   on
     *
     * @return bool
     * false =  an error occurred,
     * true =   successful
     *
     * @throws Exception
     */
    public function PowerDevice(bool $State): bool
    {
        $debugText = 'Gerät ausschalten';
        if ($State) {
            $debugText = 'Gerät einschalten';
        }
        $this->SendDebug(__FUNCTION__, $debugText, 0);
        if (!$this->CheckDevicePowerID()) {
            return false;
        }
        $powerDevice = @RequestAction($this->ReadPropertyInteger('DevicePower'), $State);
        //Try again
        if (!$powerDevice) {
            $powerDevice = @RequestAction($this->ReadPropertyInteger('DevicePower'), $State);
        }
        return $powerDevice;
    }

    #################### Private

    /**
     * Sets the volume of the device.
     *
     * @param int $Volume
     * Volume
     *
     * @return bool
     * false =  an error occurred,
     * true =   successful
     *
     * @throws Exception
     */
    private function SetDeviceVolume(int $Volume): bool
    {
        $setDeviceVolume = @RequestAction($this->ReadPropertyInteger('DeviceVolume'), $Volume);
        //Try again
        if (!$setDeviceVolume) {
            $setDeviceVolume = @RequestAction($this->ReadPropertyInteger('DeviceVolume'), $Volume);
        }
        return $setDeviceVolume;
    }

    /**
     * Calculates the next cycle.
     *
     * @return int
     * @throws Exception
     */
    private function CalculateNextCycle(): int
    {
        if (!$this->CheckDeviceVolumeID()) {
            return 0;
        }
        $dividend = $this->ReadAttributeInteger('EndTime') - time();
        $divisor = $this->ReadAttributeInteger('TargetVolume') - GetValue($this->ReadPropertyInteger('DeviceVolume'));
        //Check dividend and divisor
        if ($dividend <= 0 || $divisor <= 0) {
            $this->ToggleWakeUpMusic(false);
            return 0;
        }
        return intval(round($dividend / $divisor));
    }

    /**
     * Checks for an existing device power id.
     *
     * @return bool
     * false =  doesn't exist,
     * true =   exists
     *
     * @throws Exception
     */
    private function CheckDevicePowerID(): bool
    {
        $devicePowerID = $this->ReadPropertyInteger('DevicePower');
        if ($devicePowerID <= 1 || @!IPS_ObjectExists($devicePowerID)) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, Power (Aus/An) ist nicht vorhanden!', 0);
            return false;
        }
        return true;
    }

    /**
     * Checks for an existing device volume id.
     *
     * @return bool
     * false =  doesn't exist,
     * true =   exists
     *
     * @throws Exception
     */
    private function CheckDeviceVolumeID(): bool
    {
        $deviceVolumeID = $this->ReadPropertyInteger('DeviceVolume');
        if ($deviceVolumeID <= 1 || @!IPS_ObjectExists($deviceVolumeID)) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, Lautstärke ist nicht vorhanden!', 0);
            return false;
        }
        return true;
    }

    /**
     * Checks for an existing device presets id.
     *
     * @return bool
     * false =  doesn't exist,
     * true =   exists
     *
     * @throws Exception
     */
    private function CheckDevicePresetsID(): bool
    {
        $devicePresetsID = $this->ReadPropertyInteger('DevicePresets');
        if ($devicePresetsID <= 1 || @!IPS_ObjectExists($devicePresetsID)) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, Presets ist nicht vorhanden!', 0);
            return false;
        }
        return true;
    }
}