# Upstream Sync Workflow

This workflow automates tracking and porting changes from the original `Hacknock/lobsterjs` repository to this PHP port.

## Step 1: Check for Upstream Changes
Run the tracking script to see what has changed in the upstream repository since the last sync.

// turbo
```powershell
.\utils\Track-Upstream.ps1
```

If the script outputs "You are up to date", then stop here. No further action is required.

## Step 2: Analyze Changes
Review the output from Step 1. Focus on the files changed in `src/` of the upstream repository.
Identify the corresponding `.php` files in `src/` of this project.

## Step 3: Port the Code
For each changed file in the upstream JS:
1. Examine the Git diff for the specific file using `git diff <last_commit>..<latest_commit> -- <file_path>` inside `.upstream/lobsterjs`.
2. Find the equivalent PHP logic in `kassyi/lobsterphp`.
3. Apply the changes accurately, keeping in mind the differences between TypeScript/JavaScript and PHP.

## Step 4: Run Tests
Ensure that the changes haven't broken the existing functionality. Run the PHPUnit tests.

// turbo
```powershell
composer test
```

## Step 5: Mark as Synced
Once all changes are successfully ported and tests pass, update the sync file to track the new commit.

// turbo
```powershell
.\utils\Track-Upstream.ps1 -UpdateJson
```

## Step 6: Commit
Commit your ported changes. You can use the `/commit` workflow for this.
