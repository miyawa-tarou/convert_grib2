#include <stdio.h>
#include <string.h>
#include <math.h>

int readInt(FILE *fp, int byte);
void readBit(FILE *fp, int bit[8]);

void main(int argc, char *argv[]) {

    char    buff[30];
    int i,j;

    if (argc != 2) {
        return;
    }
    
    FILE *fg;

	if(!(fg = fopen(argv[1], "r"))){
    //if(!(fg = fopen("C:\\Users\\kmiyagi\\PhpstormProjects\\wm_source\\math\\GPV\\aaa\\20170917\\Z__C_RJTD_20170917000000_SRF_GPV_Gll5km_Prr60lv_FH07-15_grib2.bin", "rb"))){
        
		fprintf(stderr,"ファイル読み込みに失敗しました");
		return;
	}

    /**
     * Section 0
     */
    int a = fread(buff, 1, 4, fg);
    buff[4] = '\0';
    if (strcmp(buff,"GRIB")!=0) {
        printf("%s", "invalid GRIB data");
        return;
    }
    fseek(fg, 3, SEEK_CUR);

    if (fgetc(fg) != 2) {
        printf("%s", "invalid GRIB2 data");
        return;
    }

    int totalLength = readInt(fg, 8);
    int remainLength = totalLength - 16; // Sec0は固定16バイト

    /**
     * Section 1
     */
    int secLength = readInt(fg, 4);

    if (fgetc(fg) != 1) {
        printf("%s", "invalid Section 1");
        return;
    }
    fseek(fg, 7, SEEK_CUR);

    //fread(year, 2, 1, fg);
//    fgets(year, 2, fg);
//    printf("%d", year);
    int year = readInt(fg, 2);
    int month = fgetc(fg);
    int day = fgetc(fg);
    int hour = fgetc(fg);
    int min = fgetc(fg);
    int sec = fgetc(fg);

    fseek(fg, secLength - 19, SEEK_CUR);

    remainLength -= secLength;

    /**
     * Section 3
     */
    secLength = readInt(fg, 4);
    int secNum = fgetc(fg);
    if (secNum == 2) {
        fseek(fg, secLength - 5, SEEK_CUR);
        remainLength -= secLength;
        secLength = readInt(fg, 4);
        secNum = fgetc(fg);
    }

    if (secNum != 3) {
        if (secNum == 4) {
            printf("%s", "komatta");
            return;
        }
    }
    fseek(fg, 1, SEEK_CUR);
    int plotNum = readInt(fg, 4);
    fseek(fg, 20, SEEK_CUR);
    int lonPlotNum = readInt(fg, 4);
    int latPlotNum = readInt(fg, 4);

    if (plotNum != lonPlotNum * latPlotNum) {
        printf("%d", plotNum);
        printf("%s", "komatta2");
        return;
    }

    fseek(fg, 8, SEEK_CUR);
    
    // 負の値が取れてない。単位は10^-6 計算が浮動小数だと面倒なので整数のまま
    int startLat = readInt(fg, 4);
    int startLon = readInt(fg, 4);
    fseek(fg, 1, SEEK_CUR);
    int endLat = readInt(fg, 4);
    int endLon = readInt(fg, 4);

    int diffLon = readInt(fg, 4);
    int diffLat = readInt(fg, 4);

    if (startLat - diffLat * (latPlotNum - 1) != endLat) {
        printf("%d\n", startLat - diffLat * (latPlotNum - 1));
        printf("%d\n", endLat);
        printf("%s", "komatta3");
        return;
    }
    if (startLon + diffLon * (lonPlotNum - 1) != endLon) {
        printf("%f\n", startLon + diffLon * (lonPlotNum - 1));
        printf("%f\n", endLon);
        printf("%s", "komatta4");
        return;
    }
    fseek(fg, 1, SEEK_CUR);
    remainLength -= secLength;
    
    while (remainLength > 4) {

        /**
         * Section 4
         */
        secLength = readInt(fg, 4);
        secNum = fgetc(fg);

        if (secNum != 4) {
            printf("%s", "invalid Section 4");
            return;
        }

        fseek(fg, 4, SEEK_CUR);

        int paramCategory = fgetc(fg);
        int paramNum = fgetc(fg);
        int typeData = fgetc(fg);
        int type = fgetc(fg);

        fseek(fg, 5, SEEK_CUR);

        int forecastNum = readInt(fg, 4);
        int typeField = fgetc(fg);
        int typeFieldFactor = fgetc(fg);
        int typeFieldNum = readInt(fg, 4);

        fseek(fg, 6, SEEK_CUR);

        if (secLength > 34) {
            fseek(fg, secLength - 34, SEEK_CUR);
        }
        remainLength -= secLength;

        /**
         * Section 5
         */
        secLength = readInt(fg, 4);
        secNum = fgetc(fg);
        if (secNum != 5) {
            printf("%s", "invalid Section 5");
            return;
        }
        fseek(fg, 4, SEEK_CUR);

        int template = readInt(fg, 2);
        int maxV, bitNum, lngu;
        if (template == 0) {
            // TODO: 未実装
    /*
                $calc['numR'] = ReadBin::readFloat($fh);
                $calc['numE'] = ReadBin::readSingedInt($fh, 2);
                $calc['numD'] = ReadBin::readSingedInt($fh, 2);
                $calc['bitNum'] = ReadBin::readInt($fh, 1);
                $calc['exp'] = pow(2, $calc['numE']);
                $calc['base'] = pow(10, $calc['numD']);
                fread($fh, $secLength - 20);
    */

        } else if (template == 200) { // run length comporession
            bitNum = fgetc(fg);
            maxV = readInt(fg, 2);
            lngu = pow(2, bitNum) - 1 - maxV;
            fseek(fg, secLength - 14, SEEK_CUR);
        } else {
            printf("%s", "komatta5");
            return;
        }
        remainLength -= secLength;

        /**
         * Section 6
         */
        secLength = readInt(fg, 4);
        secNum = fgetc(fg);
        if (secNum != 6) {
            printf("%s", "invalid Section 6");
            return;
        }

        int bitmapMode = fgetc(fg);

        if (bitmapMode == 0) {
            int loop = secLength - 6;
            if (loop * 8 != plotNum) {
                printf("%s", "komatta6");
                return;
            }
            for (i = 0; i < loop; i++) {
                int bitTemp[8];
                readBit(fg, bitTemp);
                // TODO : 未実装

            }
            
        } else {
            //254は前のを使う、255はbitmapモードを使わない
            if (secLength > 6) {
                fseek(fg, secLength - 6, SEEK_CUR);
            }
        }
        remainLength -= secLength;

        /**
         * Section 7
         */
        secLength = readInt(fg, 4);
        secNum = fgetc(fg);
        if (secNum != 7) {
            printf("%s", "invalid Section 7");
            return;
        }

        int restLength = secLength - 5;

        int nextVal = 9999999;
        int val;
        int d[lonPlotNum][latPlotNum];
        int x = 0;
        int y = 0;
        int loopArray[100];
        int loopA = 0;

        for (i = 0; i < plotNum; i++) {
            // 0 のとき未実装


            if (template == 200) {
                if (nextVal == 9999999) {
                    if (--restLength <0) {
                        break;
                    }
                    val = fgetc(fg);

                } else {
                    val = nextVal;
                    nextVal = 9999999;
                }

                loopA = 0;

                while(1) {
                    if (--restLength <0) {
                        break;
                    }
                    //とりあえず各データ8bitとして計算
                    int data = fgetc(fg);

                    if (data <= maxV) {
                        nextVal = data;
                        break;
                    }
            
                    loopArray[loopA++] = data;
            }

            int loop = 1;
            int size = sizeof(loopArray) /  sizeof loopArray[0];
            for (j = 0; j < size; j++) {
                if (loopArray[j] == 0) {
                    break;
                }
                //printf("%d:%d\n", loopArray[j], maxV);
                loop += pow(lngu, j) * (loopArray[j] - (maxV + 1));
                loopArray[j] = 0;
            }
            if (loop < 0) {
                continue;;
            }
                                        
                for (j = 0; j < loop; j++) {
                    d[x][y] = val;
                    x++;
                    if (x >= lonPlotNum) { // 0からなので同じ値で切り替える
                        x = 0;
                        y++;
                    }
                    i++;

                    //printf("%d:%d:%d\n", x, y, val);
                    if (y >= latPlotNum) {
                        break;
                    }
                }
                i--; // for文で加算分（for文じゃなくていいのでは・・・？）


            }
                //printf("\n%d:%d\n", plotNum, i);
        }
        remainLength -= secLength;
    }

    fread(buff, 1, 4, fg);
    buff[4] = '\0';

    if (strcmp(buff,"7777")!=0) {
            printf("%s", "invalid end of GRIB2");
            return;
    }
    fclose(fg);
    return;
}

int readInt(FILE *fp, int byte) {
    int sum = 0;
    int l;
    for (l = 0; l < byte; l++) {
        sum += fgetc(fp) * pow(256, (byte - l - 1));
    }
    return sum;
}

//　未実装
void readBit(FILE *fp, int bit[8]) {


}
