# Whisp template project
Use this template project to get started in <60 seconds with [Whisp](https://github.com/WhispPHP/whisp), the pure PHP SSH server for PHP based TUIs.

⇾ [Learn more about Whisp](https://whispphp.com)

## What's included
This template sets you up with the [Whisp](https://github.com/WhispPHP/whisp) server, example TUI apps, and [Laravel Prompts](https://laravel.com/docs/12.x/prompts#main-content).

## Files

- `whisp-server.php` - Runs the SSH server on port 2020, `./whisp-server.php`
- `apps/` - Holds our apps
    - `default.php` - Called when no app is specifically requested
    - `howdy-[name].php` - Basic script to show how simple things can be, supports params
    - `prompts.php` - More complex Laravel Prompts setup to show what's supported
    - `confetti.php` - Draws confetti without Laravel Prompts
    - `clipboard.php` - Laravel Prompts app demoing copying to the user's clipboard

## Testing

Run the server, then SSH to each app:

**Run the server**
```bash
php whisp-server.php
```

**Run the howdy app**
```bash
ssh howdy-dood@localhost -p2020
```

**Run the prompts app**
We don't need to pass the app or script name here as it's the default
```bash
ssh localhost -p2020
```

**Run the confetti app**
We can also call apps using `-t appName`
```bash
ssh localhost -p2020 -t confetti
```

## Hosting
Read the [full docs](https://whispphp.com) or use the files in the `systemd` dir to run your server 24x7.


## Support & Credits

This was developed by Ashley Hindle. If you like it, please star it, share it, and let me know!

- [Bluesky](https://bsky.app/profile/ashleyhindle.com)
- [Twitter](https://twitter.com/ashleyhindle)
- Website [https://ashleyhindle.com](https://ashleyhindle.com)
