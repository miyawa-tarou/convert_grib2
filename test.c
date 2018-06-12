
#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include <math.h>
#include <png.h> // libpngが必要

int readInt(FILE *fp, int byte);
void readBit(FILE *fp, int bit[8]);
int val_to_color(int val);
void write_png(char *file_name, unsigned char **image, int width, int height);

png_color PT[11];
png_byte  PA[11];

void main(int argc, char *argv[]) {

    char    buff[30];
    int i,j;

    //PT[ 0].red = 204; PT[ 0].green = 204; PT[ 0].blue = 204; PA[ 0] = 128;	//グレー
    PT[ 0].red = 255; PT[ 0].green = 255; PT[ 0].blue = 255; PA[ 0] =   0;	//白
    PT[ 1].red = 102; PT[ 1].green = 255; PT[ 1].blue = 255; PA[ 1] = 255;	//うす水色
    PT[ 2].red =  51; PT[ 2].green = 204; PT[ 2].blue = 255; PA[ 2] = 255;	//水色
    PT[ 3].red =  17; PT[ 3].green = 102; PT[ 3].blue = 255; PA[ 3] = 255;	//青
    PT[ 4].red =  17; PT[ 4].green = 255; PT[ 4].blue =  51; PA[ 4] = 255;	//緑
    PT[ 5].red = 255; PT[ 5].green = 255; PT[ 5].blue =  51; PA[ 5] = 255;	//黄色
    PT[ 6].red = 255; PT[ 6].green = 153; PT[ 6].blue =  17; PA[ 6] = 255;	//オレンジ
    PT[ 7].red = 255; PT[ 7].green =  17; PT[ 7].blue = 255; PA[ 7] = 255;	//マゼンタ
    PT[ 8].red = 255; PT[ 8].green =  17; PT[ 8].blue =  17; PA[ 8] = 255;	//赤
    PT[ 9].red = 153; PT[ 9].green =  17; PT[ 9].blue =  51; PA[ 9] = 255;	//赤黒


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

/* 333.33333...のような場合にずれるのでなし
    if (startLat - diffLat * (latPlotNum - 1) != endLat) {
        printf("%d\n", startLat - diffLat * (latPlotNum - 1));
        printf("%d\n", endLat);
        printf("%s", "komatta3");
        return;
    }
    if (startLon + diffLon * (lonPlotNum - 1) != endLon) {
        printf("%d\n", startLon + diffLon * (lonPlotNum - 1));
        printf("%d\n", endLon);
        printf("%s", "komatta4");
        return;
    }
*/
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
        //png_bytepp d[lonPlotNum][latPlotNum];
        //png_byte *image[lonPlotNum];

        //image = malloc(lonPlotNum * sizeof(png_bytepp));
        unsigned char   **image;    
        int h = 0;
        image = (png_bytepp)malloc(latPlotNum * sizeof(png_bytep));
        for (h = 0; h < latPlotNum; h++){
            image[h] = (png_bytep)malloc(lonPlotNum * sizeof(png_byte) );
        }

        // 縮小画像を作る（代表値のみ）
        unsigned char   **image2;    
        image2 = (png_bytepp)malloc(latPlotNum * sizeof(png_bytep));
        for (h = 0; h < latPlotNum / 2; h++){
            image2[h] = (png_bytep)malloc(lonPlotNum / 2 * sizeof(png_byte) );
        }
        unsigned char   **image4;
        image4 = (png_bytepp)malloc(latPlotNum / 4 * sizeof(png_bytep));
        for (h = 0; h < latPlotNum / 4; h++){
            image4[h] = (png_bytep)malloc(lonPlotNum / 4 * sizeof(png_byte) );
        }



        int x = 0;
        int y = 0;
        int loopA = 0;

        for (i = 0; i < plotNum; i++) {
            // 0 のとき未実装


            if (template == 200) {
                if (nextVal == 9999999) {
                    if (--restLength <0) { // デクリメントは下のfgetc()の分
                        break;
                    }
                    val = fgetc(fg);

                } else {
                    val = nextVal;
                    nextVal = 9999999;
                }

                loopA = 0;
                int loopArray[100] = {};

                while(1) {
                    if (--restLength <0) {// デクリメントは下のfgetc()の分。breakしたらforも抜けるはず・・・
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

                unsigned char colorVal = (unsigned char)val_to_color(val);          
                for (j = 0; j < loop; j++) {
                    image[y][x] = colorVal;

                    if (x%2 == 0 && x % 2 == 0) {
                        image2[y/2][x/2] = colorVal;
                    }
                    if (x % 4 == 0 && x % 4 == 0) {
                        image4[y/4][x/4] = colorVal;
                    }

                    //d[x][y] = val_to_color(val);
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

        char str[3], str2[5], str4[5];
        sprintf(str, "%d", forecastNum);
        sprintf(str2, "%d-2", forecastNum);
        sprintf(str4, "%d-4", forecastNum);
        // ここで画像生成処理
        write_png(str,  image,  lonPlotNum, latPlotNum);
        write_png(str2, image2, lonPlotNum / 2, latPlotNum / 2);
        write_png(str4, image4, lonPlotNum / 4, latPlotNum / 4);

        for (h = 0; h < latPlotNum; h++){
            free(image[h]);
        }
	    free(image);
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

// 
void readBit(FILE *fp, int bit[8]) {


    /*
    int c = fgetc(fp);

    for (int i = 7; i >= 0; i--) {
        int zizyo = pow(2, i);
        if (c > zizyo) {
            c -= zizyo;
            bit[8 - i - 1] = 1;
        } else {
            bit[8 - i - 1] = 0;
        }
    }
    */
}

int val_to_color(int val)
{
    int ret = 0;
    float th[] = { 1.0,  5.0, 10.0, 20.0, 30.0, 50.0,  75.0, 100.0};
    int h;
    for(h=7;h>=1;h--){
        if(val>=th[h]){ ret = h+1; break; }
    }
    return ret;
}

void write_png(char *file_name, unsigned char **image, int width, int height)
{
	FILE            *fp;
	png_structp     png_ptr;
	png_infop       info_ptr;
	char foname[255];

	sprintf(foname, "%s.png", file_name);
	fp = fopen(foname, "wb");                            // まずファイルを開きます
	png_ptr = png_create_write_struct(                      // png_ptr構造体を確保・初期化します
	                PNG_LIBPNG_VER_STRING, NULL, NULL, NULL);
	info_ptr = png_create_info_struct(png_ptr);             // info_ptr構造体を確保・初期化します
	png_init_io(png_ptr, fp);                               // libpngにfpを知らせます
	png_set_IHDR(png_ptr, info_ptr, width, height,          // IHDRチャンク情報を設定します
	                8, PNG_COLOR_TYPE_PALETTE, PNG_INTERLACE_NONE,
	                PNG_COMPRESSION_TYPE_DEFAULT, PNG_FILTER_TYPE_DEFAULT);
	png_set_tRNS(png_ptr, info_ptr, PA, 10, NULL);
	png_set_PLTE(png_ptr, info_ptr, PT, 10);
	png_write_info(png_ptr, info_ptr);                      // PNGファイルのヘッダを書き込みます
	png_write_image(png_ptr, image);                        // 画像データを書き込みます
	png_write_end(png_ptr, info_ptr);                       // 残りの情報を書き込みます
	png_destroy_write_struct(&png_ptr, &info_ptr);          // ２つの構造体のメモリを解放します
	fclose(fp);       
}
