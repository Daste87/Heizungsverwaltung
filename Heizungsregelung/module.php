<?
    // Klassendefinition
    class Heizungsverwaltung extends IPSModule {
 
        // Überschreibt die interne IPS_Create($id) Funktion
        public function Create() {
            // Diese Zeile nicht löschen.
            parent::Create();

            $istemperature      = $this->RegisterVariableFloat("IsTemperature", $this->Translate("Is temperature"),"",0);
            $shouldtemperature  = $this->RegisterVariableInteger("ShouldTemperature", $this->Translate("Should temperature"),"",1);
            $reduction          = $this->RegisterVariableInteger("Reduction", $this->Translate("Reduction"),"",2);
            $heatingphase       = $this->RegisterVariableInteger("HeatingPhase", $this->Translate("Heatingphase"),"",3); 

            $this->EnableAction("ShouldTemperature");
            $this->EnableAction("Reduction");

            if (@$this->GetIDForIdent("heatingplan") == false) {
                $heatingplan = IPS_CreateEvent(2);
                IPS_SetParent($heatingplan, $this->InstanceID);
                IPS_SetPosition($heatingplan, 4);
                IPS_SetEventScheduleAction($heatingplan, 0, "Absenkung", 0xFF7F00, "");
                IPS_SetEventScheduleAction($heatingplan, 1, "Heizen", 0xFF0000, "");
                IPS_SetEventScheduleGroup($heatingplan, 0, 31); //Mo - Fr (1 + 2 + 4 + 8 + 16)
                IPS_SetEventScheduleGroup($heatingplan, 1, 96); //Sa + So (32 + 64)
                IPS_SetName($heatingplan, "Heizplan");
                IPS_SetIdent($heatingplan, "heatingplan");
            }

            $this->RegisterPropertyInteger("Aktor-ID", 0);
            $this->RegisterPropertyString("Schaltbefehl-An", "0");
            $this->RegisterPropertyString("Schaltbefehl-Aus", "0");
            $this->RegisterPropertyString("Schaltbefehl", "0");
            $this->RegisterPropertyInteger("Temperatur-ID", 0);
            $this->RegisterPropertyString("DeviceType", "0");





            if (!IPS_VariableProfileExists("DS_Heizphase")) {
                IPS_CreateVariableProfile("DS_Heizphase", 1);
                IPS_SetVariableProfileAssociation("DS_Heizphase", 0, "Ausgeschalten", "", 0xFFFFFF);
                IPS_SetVariableProfileAssociation("DS_Heizphase", 1, "Frostschutz", "", 0xFFFFFF);
                IPS_SetVariableProfileAssociation("DS_Heizphase", 2, "Abgesenkt", "", 0xFF7F00);
                IPS_SetVariableProfileAssociation("DS_Heizphase", 3, "Heizen", "", 0xFF0000);
            }


            if (!IPS_VariableProfileExists("DS_Temperatur")) {
                IPS_CreateVariableProfile("DS_Temperatur", 1);  //Integer
                IPS_SetVariableProfileText("DS_Temperatur", "", " °");
			    IPS_SetVariableProfileValues("DS_Temperatur",14, 25, 1 );
            }



            if (!IPS_VariableProfileExists("DS_Temperatur2")) {
                IPS_CreateVariableProfile("DS_Temperatur2", 2);  //Float
                IPS_SetVariableProfileText("DS_Temperatur2", "", " °");
			    IPS_SetVariableProfileValues("DS_Temperatur2",-30, 90, 1 );                
            }


            if (!IPS_VariableProfileExists("DSHS_Absenkung")) {
                IPS_CreateVariableProfile("DSHS_Absenkung", 1);
                IPS_SetVariableProfileText("DSHS_Absenkung", "", " °");
			    IPS_SetVariableProfileValues("DSHS_Absenkung",0, 4, 1 );
            }


            IPS_SetVariableCustomProfile($heatingphase, "DS_Heizphase");
            IPS_SetVariableCustomProfile($shouldtemperature, "DS_Temperatur");
            IPS_SetVariableCustomProfile($istemperature, "DS_Temperatur2");
            IPS_SetVariableCustomProfile($reduction, "DSHS_Absenkung");

        }
        

        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        // Moduleinstellungen
        public function ApplyChanges() {
            // Diese Zeile nicht löschen
            parent::ApplyChanges();

 



        }



    public function RequestAction($Ident, $Value) {

    $this->get_heatingplan_status(); //Aufruf Heizplan Rausfinden
    $this->set_temperature();

      switch ($Ident) {
          case 'ShouldTemperature':
            SetValue(IPS_GetObjectIDByIdent('ShouldTemperature', $this->InstanceID), $Value);
            break;
          case 'Reduction':
           SetValue(IPS_GetObjectIDByIdent('Reduction', $this->InstanceID), $Value);
            break;
            }


    }


    public function get_heatingplan_status() {
            $wochenplan = $this->wochenplan_status($this->GetIDForIdent("heatingplan"));
            if ($wochenplan == 0)
            {
                $this->SetValue("HeatingPhase", 2); #Abgesenkt
                #echo "Wochenplan Aktion: Abgesenkt --> ";
                #echo $this->ReadPropertyString("DeviceType");
            }
            else
            {
                $this->SetValue("HeatingPhase", 3); #Heizen
                #echo "Wochenplan Aktion: Heizen --> ";
                #echo $this->ReadPropertyString("DeviceType");
            }      
    }






    public function set_temperature() {          
            $heizphase = $this->GetValue("HeatingPhase");
            $soll_absenkung = $this->GetValue("Reduction");
            $soll_temperatur = $this->GetValue("ShouldTemperature");
            $ist_temperature = $this->GetValue("IsTemperature");
            $schaltbefehl_an = $this->ReadPropertyString("Schaltbefehl-An");
            $schaltbefehl_aus = $this->ReadPropertyString("Schaltbefehl-Aus");
            $modus = $this->ReadPropertyString("DeviceType");

            #echo $modus;

            if ($modus == "0" ) {
            echo "fremd";

                switch ($heizphase) {
                    case 0:
                        #Execute action "AUS"
                        $schaltbefehl_aus;
                        break;
                    case 1:
                        #Execute action "FrostSchutz"
                        if ($ist_temperature < 6) {
                            $schaltbefehl_an;     
					    }
                        else {
                            $schaltbefehl_aus;  
					    }
                        break;
                    case 2:
                        #Execute action "Absenkung"
                        if ($ist_temperature > $soll_temperatur-$soll_absenkung) {
                            $schaltbefehl_aus;     
					    }
                        else {
                            $schaltbefehl_an;  
					    }

                        break;
                    case 3:
                        #Execute action "Soll-Temperatur"
                        if ($ist_temperature > $soll_temperatur) {
                            $schaltbefehl_aus;     
					    }
                        else {
                            $schaltbefehl_an;  
					    }
                        break;
                }           
           }
           else {
         echo "selbst";
            switch ($heizphase) {
                case 0:
                    #Execute action "AUS"
                    $schaltbefehl;
                    break;
                case 1:
                    #Execute action "FrostSchutz"
                    if ($ist_temperature < 6) {
                    $schaltbefehl;    
                    }
                    break;
                case 2:
                    #Execute action "Absenkung"
                    if ($ist_temperature > $soll_temperatur-$soll_absenkung) {
                    $schaltbefehl;
                    }
                case 3:
                     #Execute action "Soll-Temperatur"
                    if ($ist_temperature > $soll_temperatur) {
                    $schaltbefehl;
                    }
                    break;
                }
         
		   }


    }


















        public function wochenplan_status($id) {
            $e = IPS_GetEvent($id);
            $actionID = false;
            //Durch alle Gruppen gehen
            foreach($e['ScheduleGroups'] as $g) {
                //Überprüfen ob die Gruppe für heute zuständig ist
                if(($g['Days'] & pow(2,date("N",time())-1)) > 0)  {
                    //Aktuellen Schaltpunkt suchen. Wir nutzen die Eigenschaft, dass die Schaltpunkte immer aufsteigend sortiert sind.
                    foreach($g['Points'] as $p) {
                       if(date("H") * 3600 + date("i") * 60 + date("s") >= $p['Start']['Hour'] * 3600 + $p['Start']['Minute'] * 60 + $p['Start']['Second']) {
                          $actionID = $p['ActionID'];
                       } else {
                          break; //Sobald wir drüber sind, können wir abbrechen.
                       }
                   }
                    break; //Sobald wir unseren Tag gefunden haben, können wir die Schleife abbrechen. Jeder Tag darf nur in genau einer Gruppe sein.
                }
            }
            #var_dump($actionID);
            return $actionID;
        }
    }
?>