; Omni POS - Windows installer
;
; Packages the whole application (including vendor/, node_modules/ and the
; pre-built public/build assets, so nothing needs the internet at install
; time except Docker Desktop itself) and wraps start.bat/stop.bat with a
; normal Windows install/uninstall experience.
;
; Requires Inno Setup 6 (https://jrsoftware.org/isinfo.php) to compile:
;   "C:\Program Files (x86)\Inno Setup 6\ISCC.exe" installer\omnipos.iss
; Output lands in installer\dist\OmniPOS-Setup-<version>.exe
;
; Before compiling a release build:
;   composer install --no-dev --optimize-autoloader
;   npm install && npm run build
; (so vendor/ and node_modules/ + public/build are all present and current)

#define MyAppName "Omni POS"
#define MyAppVersion "1.0.0"
#define MyAppPublisher "Omni POS"
#define MyAppURL "https://github.com/"

[Setup]
AppId={{B04F8BC4-E4AF-450D-A89B-C503DB82C8EE}}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
DefaultDirName={localappdata}\{#MyAppName}
DefaultGroupName={#MyAppName}
DisableProgramGroupPage=yes
; Files only need per-user write access - Docker Desktop handles its own
; elevation separately when it needs it.
PrivilegesRequired=lowest
OutputDir=dist
OutputBaseFilename=OmniPOS-Setup-{#MyAppVersion}
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
SetupIconFile=..\public\favicon.ico
UninstallDisplayIcon={app}\public\favicon.ico

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "Create a &desktop shortcut"; GroupDescription: "Additional shortcuts:"

[Files]
; Everything the app needs to run, pre-built - no composer/npm required
; on the target machine.
Source: "..\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs; \
    Excludes: ".git\*,.git,.env,installer\dist\*,installer\dist,storage\logs\*,storage\framework\cache\data\*,storage\framework\sessions\*,storage\framework\views\*,_start_test_out.txt,_start_test_err.txt,_empty_stdin.txt"

[Icons]
Name: "{group}\{#MyAppName}"; Filename: "{app}\start.bat"; WorkingDir: "{app}"; IconFilename: "{app}\public\favicon.ico"
Name: "{group}\Stop {#MyAppName}"; Filename: "{app}\stop.bat"; WorkingDir: "{app}"; IconFilename: "{app}\public\favicon.ico"
Name: "{group}\Uninstall {#MyAppName}"; Filename: "{uninstallexe}"
Name: "{userdesktop}\{#MyAppName}"; Filename: "{app}\start.bat"; WorkingDir: "{app}"; IconFilename: "{app}\public\favicon.ico"; Tasks: desktopicon

[Run]
Filename: "{app}\start.bat"; Description: "Launch {#MyAppName} now"; Flags: postinstall nowait skipifsilent shellexec runasoriginaluser

[UninstallRun]
; Stops the containers but - deliberately - never deletes the Docker
; volumes, so uninstalling never silently destroys business data (orders,
; products, images...). See the farewell message below for how to wipe it.
Filename: "{app}\stop.bat"; RunOnceId: "StopContainers"; Flags: runhidden; WorkingDir: "{app}"

[Code]
function DockerDesktopInstalled(): Boolean;
begin
  Result := FileExists(ExpandConstant('{pf}\Docker\Docker\Docker Desktop.exe'))
    or FileExists(ExpandConstant('{commonpf64}\Docker\Docker\Docker Desktop.exe'));
end;

function InitializeSetup(): Boolean;
var
  ErrorCode: Integer;
begin
  Result := True;
  if not DockerDesktopInstalled() then
  begin
    if MsgBox('Omni POS runs on Docker Desktop, which was not found on this computer.' + #13#10 + #13#10 +
              'Click OK to open the Docker Desktop download page. Install it (it will ask to restart your computer), then run this setup again.' + #13#10 + #13#10 +
              'Click Cancel to continue installing the app files anyway - you can install Docker Desktop later and use the desktop shortcut once it''s ready.',
              mbConfirmation, MB_OKCANCEL) = IDOK then
    begin
      ShellExec('open', 'https://www.docker.com/products/docker-desktop/', '', '', SW_SHOW, ewNoWait, ErrorCode);
      Result := False;
    end;
  end;
end;

procedure DeinitializeUninstall();
begin
  MsgBox('Omni POS has been removed from this computer.' + #13#10 + #13#10 +
         'Your data (products, orders, images, database) is still safely stored in Docker''s volumes - reinstalling picks up right where you left off.' + #13#10 + #13#10 +
         'To permanently delete that data too, run "docker volume ls" in a terminal, find the ones containing "sail-" or "minio", then "docker volume rm <name>" for each.',
         mbInformation, MB_OK);
end;
