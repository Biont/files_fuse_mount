# Files FUSE Mount

Expose a user's Nextcloud storage as a filesystem. Every reading & writing operation in this filesystem is controlled by Nextcloud and its filesystem API.
This has some key benefits over direct filesystem access:

* Shares are working
* Flows are properly triggered
* No rescan of file changes required

## Use cases:
* Create a pool of shared media with colleagues and family. Then mount the whole library in some external media player like Jellyfin
* Expose a user's storage as a Samba share without worrying about consistency with Nextcloud. 
* Use powerful third-party server tools for processing and organizing data and see changes immediately reflected  in Nextcloud

## Caveats
This a very very rudimentary implementation. It currently supports:
* Directory listing
* Read operations
* Write operations
* File deletion

So there is a lot of stuff missing that you might expect:
* Polling
* Symlinks
* Integration of common linux concepts like ownership/permissions and locking (where possible, correlating Nextcloud concepts are applied though)

# Troubleshooting
## Failed loading 'libfuse.so'
Make sure your system has the required packages. On Debian for example, you need `fuse` and `libfuse-dev`
