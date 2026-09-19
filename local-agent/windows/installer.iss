#define MyAppName "Aptech Attendance Local Agent"
#define MyAppVersion "1.0.0"
#define MyAppPublisher "Ajaz Engineering"
#define MyAppExeName "AptechAttendanceAgentSetup.exe"

[Setup]
AppId={{D90D73B2-79B1-4D0B-A70E-1D84F05C05D8}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
DefaultDirName={autopf}\Aptech Attendance Local Agent
DisableDirPage=yes
DisableProgramGroupPage=yes
PrivilegesRequired=admin
OutputDir=..\..\dist
OutputBaseFilename=AptechAttendanceAgentSetup
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
Uninstallable=no

[Files]
Source: "..\..\dist\local-agent\*"; DestDir: "{tmp}\AptechAttendanceAgent"; Flags: recursesubdirs createallsubdirs ignoreversion

[Run]
Filename: "{sys}\WindowsPowerShell\v1.0\powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{tmp}\AptechAttendanceAgent\windows\install.ps1"" -ApiBaseUrl ""{code:GetPortalUrl}"" -AccessToken ""{code:GetAccessToken}"""; Flags: waituntilterminated; StatusMsg: "Installing and starting Attendance Local Agent..."
[Code]
var
  PortalPage: TInputQueryWizardPage;
  TokenPage: TInputQueryWizardPage;
  InstallSucceeded: Boolean;

procedure InitializeWizard;
begin
  PortalPage := CreateInputQueryPage(wpSelectDir,
    'Connect to HR Portal',
    'Enter the hosted HR portal URL.',
    'The Local Agent uses HTTPS to receive its assigned devices and upload attendance.');
  PortalPage.Add('Portal URL:', False);
  PortalPage.Values[0] := 'https://ajazengineering.com';

  TokenPage := CreateInputQueryPage(PortalPage.ID,
    'Local Agent Access Token',
    'Enter the access token shown in Branches & Devices.',
    'The token is shown when the first Local Agent is created or when its credential is rotated.');
  TokenPage.Add('Access token:', True);
end;

function NextButtonClick(CurPageID: Integer): Boolean;
begin
  Result := True;
  if CurPageID = PortalPage.ID then
  begin
    if Pos('https://', Lowercase(Trim(PortalPage.Values[0]))) <> 1 then
    begin
      MsgBox('Portal URL must start with https://', mbError, MB_OK);
      Result := False;
    end;
  end
  else if CurPageID = TokenPage.ID then
  begin
    if Trim(TokenPage.Values[0]) = '' then
    begin
      MsgBox('Local Agent access token is required.', mbError, MB_OK);
      Result := False;
    end;
  end;
end;

procedure CurStepChanged(CurStep: TSetupStep);
var
  ResultCode: Integer;
  PowerShellExe: String;
  VerifyCommand: String;
begin
  if CurStep = ssPostInstall then
  begin
    PowerShellExe := ExpandConstant('{sys}\WindowsPowerShell\v1.0\powershell.exe');
    VerifyCommand := '-NoProfile -ExecutionPolicy Bypass -Command "if (Get-ScheduledTask -TaskName ''Aptech Attendance Sync Agent'' -ErrorAction SilentlyContinue) { exit 0 } else { exit 41 }"';

    if not Exec(PowerShellExe, VerifyCommand, '', SW_HIDE, ewWaitUntilTerminated, ResultCode) then
      RaiseException('Unable to verify the Local Agent Scheduled Task.');

    if ResultCode <> 0 then
      RaiseException('Local Agent installation failed because the Scheduled Task was not created.');

    InstallSucceeded := True;
  end;
end;

function GetCustomSetupExitCode: Integer;
begin
  if InstallSucceeded then
    Result := 0
  else
    Result := 41;
end;

function PrepareToInstall(var NeedsRestart: Boolean): String;
begin
  Result := '';
  if Trim(TokenPage.Values[0]) = '' then
    Result := 'Local Agent access token is required.';
end;

function GetPortalUrl(Param: String): String;
begin
  Result := Trim(PortalPage.Values[0]);
end;

function GetAccessToken(Param: String): String;
begin
  Result := Trim(TokenPage.Values[0]);
end;
