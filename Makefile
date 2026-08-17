VERSION=4.0.0-pre4
SRC=CHANGELOG inc conf utils index.php languages views op out README README.Notification drop-tables-innodb.sql styles js TODO LICENSE Makefile webdav install
#restapi webapp

dist:
	mkdir -p tmp/letoDMS-$(VERSION)
	cp -a $(SRC) tmp/letoDMS-$(VERSION)
	(cd tmp; tar --exclude=.svn -czvf ../LetoDMS-$(VERSION).tar.gz letoDMS-$(VERSION))
	rm -rf tmp

pear:
	(cd LetoDMS_Core/; pear package)
	(cd LetoDMS_Lucene/; pear package)
	(cd LetoDMS_Preview/; pear package)

webdav:
	mkdir -p tmp/letoDMS-webdav-$(VERSION)
	cp webdav/* tmp/letoDMS-webdav-$(VERSION)
	(cd tmp; tar --exclude=.svn -czvf ../LetoDMS-webdav-$(VERSION).tar.gz letoDMS-webdav-$(VERSION))
	rm -rf tmp

doc:
	phpdoc -d LetoDMS_Core --ignore 'getusers.php,getfoldertree.php,config.php,reverselookup.php' -t html

.PHONY: webdav
