#!/bin/sh
if [ "$1" = "" ]; then
    echo "Please specifiy the grid master ip address : $0 10.1.11.1"
else
    GRID_MASTER=$1
    URL=https://$GRID_MASTER/api/dist/CPAN/authors/id/INFOBLOX
    echo "Fetching API file name from $URL/"
    FILENAME=`curl -s -k $URL/ | grep tar | cut -d "\"" -f 2 | cut -d "." -f 1,2`
    if [ "$FILENAME" = "" ]; then
        echo "Could not get API filename, exiting"
    else
        FOUND_VERSION=`echo $FILENAME | cut -d '-' -f 2`
        CURRENT_VERSION=`perl -MIB_PAPI_version -e 'print $VERSION' 2>/dev/null`
        if [ "$CURRENT_VERSION" = "" ]; then
            echo "Warning : could not get current API version, continue yes/[no] ?"
            read INPUT
            if [ "$INPUT" != "yes" ]; then
                echo "Installation cancelled, cleaning up"
                /bin/rm $FILENAME.tar.gz
                /bin/rm -rf $FILENAME
                exit
            fi
            CURRENT_VERSION=0
        fi
        RESULT=`expr $FOUND_VERSION \= $CURRENT_VERSION`
        echo "Current version is $CURRENT_VERSION, found version is $FOUND_VERSION"
        if [ "$RESULT" -eq "1" ]; then
            echo "Update not needed"
        else
            RESULT=`expr $FOUND_VERSION \< $CURRENT_VERSION`
            if [ "$RESULT" -eq "1" ]; then
                echo "Warning : found version is a downgrade, continue yes/[no] ?"
                read INPUT
                if [ "$INPUT" != "yes" ]; then
                    echo "Downgrade cancelled, cleaning up"
                    /bin/rm $FILENAME.tar.gz
                    /bin/rm -rf $FILENAME
                    exit
                fi
            fi            
            echo "Downloading $URL/$FILENAME.tar.gz"
            curl -s -k -o $FILENAME.tar.gz $URL/$FILENAME.tar.gz
            tar -zxf $FILENAME.tar.gz
            cd $FILENAME
            perl Makefile.PL
            echo "Compiling API"
            make > /dev/null
            echo "Installing API"
            sudo make install > /dev/null
            cd ..
            echo "Cleaning up"
            /bin/rm $FILENAME.tar.gz
            /bin/rm -rf $FILENAME
        fi
    fi
fi
