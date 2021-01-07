# GeoApiProxy

Proxies the subset of the GeoNames API used by GeoSetter and uses an alternative reverse geocoding service for hopefully better results.

## Usage

You'll need a web server (preferably Apache, otherwise you need to set up the URL rewriting yourself) and PHP 7. If you have some web space available, you might be able to simply use that, otherwise something running locally on your computer is fine, too. You'll also need an API key for the OpenCage geocoding service and insert it into its required place in proxy.php.
Then, copy everything into a folder of your choice underneath your web root. Note that due to how GeoSetter accesses the GeoNames API, the proxy service *must* be reachable via HTTP, *not* HTTPS!

Once you've done that, you can test it by opening http://\<your-domain>/\<your-path>/debug?lat=51.477&lng=0&username=\<your GeoNames API user name>. If everything is working correctly, you should get some reverse geocoding data for the Greenwich Observatory.
At that point, you might also want to change the default language preferences, as the default values for LANGUAGE_MAPPING might not be that useful if you aren't me :-)

Next, before launching GeoSetter you need to clear it's location data cache by deleting *%AppData%\GeoSetter\location_cache.dat*, otherwise you'll be getting stale data for any coordinates you've already looked up within GeoSetter. This cache also needs to be deleted any time you make any changes to the workings of this script, in order to force GeoSetter to get the data afresh from the internet.
Finally, open GeoSetter's settings, switch to the *Internet* tab and replace the default address of the GeoNames API with the address to GeoApiProxy on your web server. Happy geocoding!

## Licenses

GeoApiProxy is (c) Jan Henning, 2020 – 2021 and is provided under the Mozilla Public License 2.0 (see LICENSE.md)

The _OpenCage Geocoding API Library_ is used under the following license:

Copyright (c) 2019 OpenCage GmbH (https://opencagedata.com) <br>
Copyright (c) 2015 OpenCageData (https://opencagedata.com) <br>
Copyright (c) 2014 Lokku Limited (http://lokku.com/) <br>
Copyright (c) 2014 Gary Gale (http://www/garygale.com/)

All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.

2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
