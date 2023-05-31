# Aufwachmusik  

Diese Instanz schaltet ein Gerät und simuliert eine Aufwachmusik für ein entspanntes aufwachen.

Für dieses Modul besteht kein Anspruch auf Fehlerfreiheit, Weiterentwicklung, sonstige Unterstützung oder Support.  
Bevor das Modul installiert wird, sollte unbedingt ein Backup von IP-Symcon durchgeführt werden.  
Der Entwickler haftet nicht für eventuell auftretende Datenverluste oder sonstige Schäden.  
Der Nutzer stimmt den o.a. Bedingungen, sowie den Lizenzbedingungen ausdrücklich zu.

## Wochenplan

Die Einschaltfunktion kann auch über den Wochenplan ausgelöst werden.

## Funktionen

Mit dieser Funktion kann die Aufwachmusik geschaltet werden.

```text
boolean AWM_ToggleWakeUpMusic(integer $InstanceID, boolean $State);
```

Konnte der Befehl erfolgreich ausgeführt werden, liefert er als Ergebnis `TRUE`, andernfalls `FALSE`.

| Parameter    | Beschreibung   | Wert                        |
|--------------|----------------|-----------------------------|
| `InstanceID` | ID der Instanz | z.B. 12345                  |
| `State`      | Status         | false = Aus, true = An      |

**Beispiel:**

Die Aufwachmusik soll eingeschaltet werden.

```php
$id = 12345;
$result = AWM_ToggleWakeUpMusic($id, true);
var_dump($result);
```

## Ausnahmen 

| Vorgang                     | Gerätestatus                                     | Aktion  |
|-----------------------------|--------------------------------------------------|---------|
| Beim Einschalten            | Gerät ist bereits eingeschaltet                  | Abbruch |
| Bei Erhöhung der Lautstärke | Gerät wurde inzwischen wieder ausgeschaltet      | Abbruch |
| Bei Erhöhung der Lautstärke | Gerätelautstärke wurde bereits manuell verändert | Abbruch |
