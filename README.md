# Files FUSE Mount
[![Test](https://github.com/Biont/files_fuse_mount/actions/workflows/test.yaml/badge.svg)](https://github.com/Biont/files_fuse_mount/actions/workflows/test.yaml)

Expose a user's Nextcloud storage as a filesystem. Every reading & writing operation in this filesystem is controlled by Nextcloud and its filesystem API.
This has some key benefits over direct filesystem access:

* Shares are working
* Flows are properly triggered
* No rescan of file changes required

## Use cases:
* Create a pool of shared media with colleagues and family. Then mount the whole library in some external media player like Jellyfin
* Expose a user's storage as a Samba share without worrying about consistency with Nextcloud.
* Replace an existing WebDAV mount. Benchmarks are needed, but chances are this solution here will perform a LOT better
* Use powerful third-party server tools for processing and organizing data and see changes immediately reflected in Nextcloud

## Requirements
 * Your system needs to be set up for mounting FUSE filesystems: You need the have `libfuse-dev` and `fuse` installed.
 * You need PHP 7.4+ with `ext-ffi`

## Usage
This app has no GUI and probably won't ever need one. For now, it just ships a new CLI command called `files:fuse-mount`. It supports specifying one or more users and a mount point.
For example, to mount a single user directory, run
`./occ files:fuse-mount --user john /mnt/nextcloud-fuse/john`

You can also pass multiple users. In this case the root of the mount point will contain all individual user folders as subdirectories
`./occ files:fuse-mount -u john -u jane /mnt/nextcloud-fuse/john`

The command will stay in the foreground as long as the mount is active. To unmount and terminate the process, use `fusermount -u <MOUNTPOINT>`
## Caveats
This a very very rudimentary implementation. It currently supports:
* Directory listing
* Read operations
* Write operations
* File deletion

So there is a lot of stuff missing that you might expect:
* Polling (Might need to look into PHP8.1 Fibers for this one)
* Symlinks (A limited implementation that enables sharing between users could work. Otherwise it just does not make sense to support symlinks)
* Integration of common linux concepts like ownership/permissions and locking (where possible, correlating Nextcloud concepts are applied though)

# Troubleshooting

*Failed loading 'libfuse.so'*
Make sure your system has the required packages. On Debian for example, you need `fuse` and `libfuse-dev`

*`/dev/fuse` not found!*
[Check that your user has acces to that file](https://superuser.com/questions/466304/how-do-i-make-sshfs-work-in-debian-i-get-dev-fuse-permission-denied)
If you're seeing the error inside a container, ensure that you are passing the `/dev/fuse` device into it
