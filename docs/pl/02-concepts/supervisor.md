# Nadzorca procesu

Harmonogram nadzorcy jest zaprojektowany dla odłączonych procesów długoterminowych: słuchaczy, demon transportu lub inny czas działania należący do pracownika. sTask nie wie, jak sprawdzić PID lub tętno danego procesu; Robi się to za pomocą adaptera w klasie Work.

## Kontrakt

Pracownik jednocześnie pozostaje regularnym `TaskInterface`/`BaseWorker` i dodaje:

```php
use Seiger\sTask\Contracts\SupervisorWorkerInterface;
use Seiger\sTask\Support\SupervisorStatus;

interface SupervisorWorkerInterface
{
    public function supervisorKey(): string;
    public function inspectSupervisor(): SupervisorStatus;
    public function startSupervisor(): SupervisorStatus;
    public function restartSupervisor(): SupervisorStatus;
    public function supervisorStartupGraceSeconds(): int;
}
```

`inspectSupervisor()` nie powinno zmieniać czasu działania. `startSupervisor()` i `restartSupervisor()` powinny szybko wrócić po odłączonym starcie; Nie muszą czekać, aż Demon się skończy.

## Status nadzorcy

```php
return new SupervisorStatus(
    state: SupervisorState::Healthy,
    pid: 18422,
    heartbeatAt: now()->subSeconds(5),
    startedAt: now()->subHours(3),
    uptimeSeconds: 10800,
    message: 'Listener is receiving updates',
    fingerprint: 'listener:healthy:v1',
);
```

Pola:

- `state` — `healthy`, `starting`, `degraded`, `failed`, `stopped`;
- `pid` — informacyjny PID, unieważniający;
- `heartbeatAt` — ostatnie potwierdzenie życia;
- `startedAt` i `uptimeSeconds` — czas uruchamiania/uruchamiania procesu, który pokazuje interfejs użytkownika;
- `message` — diagnostyka bezpieczna dla menedżera;
- `fingerprint` jest stabilnym adapterem odciskiem palca. Jeśli nie jest to określone, sTask haszuje stan + wiadomość.

Nie uwzględniać tokenów wiadomości/odcisków palców, sekretów sesji ani pełnych linii poleceń z poświadczeniami.

## Jeden przepustka do harmonogramu

1. sTask otrzymuje niepusty `supervisorKey()`.
2. Formy `key_hash = sha256(worker_id + ':' + supervisor_key)`.
3. Blokada pamięci podręcznej trwa `stask:supervisor:{key_hash}` 55 sekund.
4. Powoduje inspekcję tylko do odczytu.
5. Jeśli stan `stopped`, `degraded` lub `failed` i wygasła łaska startowa, wywołuje start/restart.
6. Odnawia `s_supervisor_states`.
7. Tworzy ostatni wiersz zadania tylko dla istotnego zdarzenia: rozpoczęte, wznowione, odzyskane, nieudane, zatrzymane, zmiana stanu/diagnostyki.

Zdrowy snapshot z tymi samymi odciskami palców aktualizuje tylko `last_seen_at`, tętno/uptime i `repeat_count`; Nowe zadanie nie powstaje co minutę.

## Łaska startu

`launch_requested_at` przypomina sobie ostatnią prośbę o start. Dopóki `max(1, supervisorStartupGraceSeconds())` nie minie, restart/restart jest tłumiony. Po zdrowym stanie pole jest oczyszczane.

Grace nie udowadnia, że proces się rozpoczął. Adapter musi prawidłowo rozróżniać `starting`, `healthy`, `degraded`, `failed`, `stopped` na podstawie PID/pulsu/czasu działania.

## Przejścia i zadania zdarzeń

| Obserwacja | Akcja | Zadanie zdarzenia |
| --- | --- | --- |
| Pierwszy `stopped` | `startSupervisor()` | `started`, czyli `failed/stopped` przez status zwrotu |
| Istniejące `degraded/failed` | `restartSupervisor()` | `restarted`, czyli porażka |
| `starting` w łasce | Start się nie powtarza | tylko przy zmianie stanu/odcisku palca |
| Niezdrowe → `healthy` | stan trwa | `recovered` |
| stan zmieniony | stan trwa | wydarzenie z nowym stanem |
| odcisk palca się zmienił | diagnostyka trwa | `diagnostic_changed` |
| zdrowy niezmieniony | Odświeżanie kursora | brak zadania |

Zadanie zdarzenia ma akcję `supervisor`, `started_by = 0`, `max_attempts = 1`, znaczniki czasu `now()`. Niepowodzenie/degradacja/zatrzymanie staje się nieudane zadaniem; Inne wydarzenia — zakończone.

## Zakład dla pracowników

Dla harmonogramu przełożonego tabela pokazuje chip z `activity-heartbeat`. Jeśli stan jest zdrowy i `uptime_seconds` znany, odznaka pokazuje sformatowany czas dostępności przez `niceEta()` zamiast słowa "Works". Modal pokazuje klucz, stan, PID, tętno, czas działania, ostatnią diagnozę i czas przejścia.

## Stan tabeli i wzrost

`key_hash` unikalny, więc jeden wiersz jest `worker_id + supervisor_key` aktualizowany dla tej samej pary. Normalne bicie serca nie tworzy wierszy historycznych. Tabela rośnie tylko wtedy, gdy pojawiają się nowe identyfikatory/klucze pracowników; Nie ma automatycznego przycinania.

Po odinstalowaniu lub ponownym zainstalowaniu workerów, wiersze stanu osieroconego nie są czyszczone kluczem obcym, ponieważ schemat nie tworzy FK/kaskady. Zrób rutynowe czyszczenie dopiero po potwierdzeniu, że pracownik/klucz już nie istnieje.

## Lista kontrolna operacyjna

- odłączony start i szybki powrót;
- klucz nadzorcy jest stabilny i niepusty;
- bicie serca ma wyraźny timeout;
- PID jest sprawdzany razem z tożsamością procesu, nie tylko `posix_kill($pid, 0)`;
- idempotentny start/restart;
- Czas rozruchu jest dłuższy niż typowy czas rozruchu;
- zewnętrzny systemd/Supervisor nie koliduje z polityką restartu adaptera;
- Logi i stan nie zawierają sekretów.
